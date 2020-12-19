<?php declare(strict_types = 1);

/**
 * Exchange.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:RabbitMqPlugin!
 * @subpackage     common
 * @since          0.1.0
 *
 * @date           10.07.20
 */

namespace FastyBird\RabbitMqPlugin;

use Bunny;
use Closure;
use FastyBird\ModulesMetadata\Loaders as ModulesMetadataLoaders;
use Nette;
use Nette\Utils;
use React\Promise;
use Throwable;

/**
 * RabbitMQ exchange builder
 *
 * @package        FastyBird:RabbitMqPlugin!
 * @subpackage     common
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @method onBeforeConsumeMessage(Bunny\Message $message)
 * @method onAfterConsumeMessage(Bunny\Message $message)
 */
final class Exchange
{

	use Nette\SmartObject;

	private const EXCHANGE_TYPE = 'topic';
	private const MAX_CONSUMED_MESSAGES = 50;

	/** @var Closure[] */
	public array $onBeforeConsumeMessage = [];

	/** @var Closure[] */
	public array $onAfterConsumeMessage = [];

	/** @var string */
	private string $origin;

	/** @var string[]|null */
	private ?array $routingKeys;

	/** @var int */
	private int $consumedMessagesCnt = 0;

	/** @var Connections\IRabbitMqConnection */
	private Connections\IRabbitMqConnection $connection;

	/** @var Consumer\IConsumer */
	private Consumer\IConsumer $consumer;

	/** @var ModulesMetadataLoaders\IMetadataLoader */
	private ModulesMetadataLoaders\IMetadataLoader $metadataLoader;

	/** @var Bunny\Client|null */
	private ?Bunny\Client $client = null;

	/** @var Bunny\Async\Client|null */
	private ?Bunny\Async\Client $asyncClient = null;

	/**
	 * @param string $origin
	 * @param Connections\IRabbitMqConnection $connection
	 * @param Consumer\IConsumer $consumer
	 * @param ModulesMetadataLoaders\IMetadataLoader $metadataLoader
	 * @param string[] $routingKeys
	 */
	public function __construct(
		string $origin,
		Connections\IRabbitMqConnection $connection,
		Consumer\IConsumer $consumer,
		ModulesMetadataLoaders\IMetadataLoader $metadataLoader,
		?array $routingKeys = null
	) {
		$this->origin = $origin;

		$this->connection = $connection;
		$this->consumer = $consumer;

		$this->metadataLoader = $metadataLoader;

		$this->routingKeys = $routingKeys;
	}

	/**
	 * @return void
	 */
	public function initialize(): void
	{
		if (!$this->consumer->hasConsumers()) {
			throw new Exceptions\InvalidStateException('No consumer handler registered. Exchange could not be initialized');
		}

		$this->client = $this->connection->getClient();

		$channel = $this->connection->getChannel();

		$channel->qos(0, 5);

		$this->processChannel($channel);
	}

	/**
	 * @return void
	 *
	 * @throws Throwable
	 */
	public function initializeAsync(): void
	{
		if (!$this->consumer->hasConsumers()) {
			throw new Exceptions\InvalidStateException('No consumer handler registered. Exchange could not be initialized');
		}

		$this->asyncClient = $this->connection->getAsyncClient();

		$promise = $this->asyncClient
			->connect()
			->then(function (Bunny\Async\Client $client) {
				return $client->channel();
			})
			->then(function (Bunny\Channel $channel): Promise\PromiseInterface {
				$this->connection->setChannel($channel);

				$qosResult = $channel->qos(0, 5);

				if ($qosResult instanceof Promise\ExtendedPromiseInterface) {
					return $qosResult
						->then(function () use ($channel): Bunny\Channel {
							return $channel;
						});
				}

				throw new Exceptions\InvalidStateException('RabbitMQ QoS could not be configured');
			})
			->then(function (Bunny\Channel $channel): void {
				$this->processChannel($channel);
			});

		if ($promise instanceof Promise\ExtendedPromiseInterface) {
			$promise->done();
		}
	}

	/**
	 * @param Bunny\Channel $channel
	 *
	 * @return void
	 */
	private function processChannel(
		Bunny\Channel $channel
	): void {
		$autoDeleteQueue = false;
		$queueName = $this->consumer->getQueueName();

		if ($queueName === null) {
			$queueName = 'rabbit.plugin_' . $this->origin;

			$autoDeleteQueue = true;
		}

		// Create exchange
		$channel
			// Try to create exchange
			->exchangeDeclare(
				Constants::RABBIT_MQ_MESSAGE_BUS_EXCHANGE_NAME,
				self::EXCHANGE_TYPE,
				false,
				true
			);

		// Create queue to connect to...
		$channel->queueDeclare(
			$queueName,
			false,
			true,
			false,
			$autoDeleteQueue
		);

		// ...and bind it to the exchange
		if ($this->routingKeys === null) {
			$metadata = $this->metadataLoader->load();

			if ($metadata->offsetExists($this->origin)) {
				$moduleMetadata = $metadata->offsetGet($this->origin);

				/** @var Utils\ArrayHash $moduleVersionMetadata */
				foreach ($moduleMetadata as $moduleVersionMetadata) {
					if ($moduleVersionMetadata->offsetGet('version') === '*') {
						/** @var Utils\ArrayHash $moduleGlobalMetadata */
						$moduleGlobalMetadata = $moduleVersionMetadata->offsetGet('metadata');

						foreach ($moduleGlobalMetadata->offsetGet('exchange') as $routingKey) {
							$channel->queueBind(
								$queueName,
								Constants::RABBIT_MQ_MESSAGE_BUS_EXCHANGE_NAME,
								$routingKey
							);
						}
					}
				}
			}

		} else {
			foreach ($this->routingKeys as $routingKey) {
				$channel->queueBind(
					$queueName,
					Constants::RABBIT_MQ_MESSAGE_BUS_EXCHANGE_NAME,
					$routingKey
				);
			}
		}

		$channel->consume(
			function (Bunny\Message $message, Bunny\Channel $channel, Bunny\AbstractClient $client): void {
				$this->onBeforeConsumeMessage($message);

				$result = $this->consumer->consume($message);

				switch ($result) {
					case Consumer\IConsumer::MESSAGE_ACK:
						$channel->ack($message); // Acknowledge message
						break;

					case Consumer\IConsumer::MESSAGE_NACK:
						$channel->nack($message); // Message will be re-queued
						break;

					case Consumer\IConsumer::MESSAGE_REJECT:
						$channel->reject($message, false); // Message will be discarded
						break;

					case Consumer\IConsumer::MESSAGE_REJECT_AND_TERMINATE:
						$channel->reject($message, false); // Message will be discarded

						if ($client instanceof Bunny\Client || $client instanceof Bunny\Async\Client) {
							$client->stop();
						}
						break;

					default:
						throw new Exceptions\InvalidArgumentException('Unknown return value of message bus consumer');
				}

				if (
					$client instanceof Bunny\Client
					&& ++$this->consumedMessagesCnt >= self::MAX_CONSUMED_MESSAGES
				) {
					$client->stop();
				}

				$this->onAfterConsumeMessage($message);
			},
			$queueName
		);
	}

	/**
	 * @return void
	 */
	public function run(): void
	{
		if ($this->client === null && $this->asyncClient === null) {
			throw new Exceptions\InvalidStateException('Exchange is not initialized');
		}

		if ($this->client !== null) {
			$this->client->run();
		}

		if ($this->asyncClient !== null) {
			throw new Exceptions\InvalidStateException('Exchange have to be started via React/EventLoop service');
		}
	}

	/**
	 * @return void
	 */
	public function stop(): void
	{
		if ($this->client !== null) {
			$this->client->stop();
		}

		if ($this->asyncClient !== null) {
			throw new Exceptions\InvalidStateException('Exchange have to be stopped via React/EventLoop service');
		}
	}

}
