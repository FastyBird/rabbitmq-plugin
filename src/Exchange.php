<?php declare(strict_types = 1);

/**
 * Exchange.php
 *
 * @license        More in license.md
 * @copyright      https://fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:NodeExchange!
 * @subpackage     common
 * @since          0.1.0
 *
 * @date           10.07.20
 */

namespace FastyBird\NodeExchange;

use Bunny;
use FastyBird\NodeExchange\Exceptions\InvalidStateException;
use Nette;
use React\Promise;
use Throwable;

/**
 * RabbitMQ exchange builder
 *
 * @package        FastyBird:NodeExchange!
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
	public $onBeforeConsumeMessage = [];

	/** @var Closure[] */
	public $onAfterConsumeMessage = [];

	/** @var string[] */
	private $routingKeys;

	/** @var int */
	private $consumedMessagesCnt = 0;

	/** @var Connections\IRabbitMqConnection */
	private $connection;

	/** @var Consumers\IExchangeConsumer */
	private $consumer;

	/** @var Bunny\Client|Bunny\Async\Client|null */
	private $client = null;

	/**
	 * @param Connections\IRabbitMqConnection $connection
	 * @param Consumers\IExchangeConsumer $consumer
	 * @param string[] $routingKeys
	 */
	public function __construct(
		Connections\IRabbitMqConnection $connection,
		Consumers\IExchangeConsumer $consumer,
		array $routingKeys = []
	) {
		$this->connection = $connection;
		$this->consumer = $consumer;

		$this->routingKeys = $routingKeys;
	}

	/**
	 * @return void
	 */
	public function initialize(): void
	{
		if (!$this->consumer->hasHandlers()) {
			throw new InvalidStateException('No consumer handler registered. Exchange could not be initialized');
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
		if (!$this->consumer->hasHandlers()) {
			throw new InvalidStateException('No consumer handler registered. Exchange could not be initialized');
		}

		$this->client = $this->connection->getAsyncClient();

		$this->client
			->connect()
			->then(function (Bunny\Async\Client $client) {
				return $client->channel();
			})
			->then(function (Bunny\Channel $channel): Promise\PromiseInterface {
				$this->connection->setChannel($channel);

				$qosResult = $channel->qos(0, 5);

				if ($qosResult instanceof Promise\PromiseInterface) {
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
	}

	/**
	 * @return void
	 */
	public function run(): void
	{
		if ($this->client === null) {
			throw new Exceptions\InvalidStateException('Exchange is not initialized');
		}

		if ($this->client instanceof Bunny\Client) {
			$this->client->run();

		} else {
			throw new Exceptions\InvalidStateException('Exchange have to be started via React/EventLoop service');
		}
	}

	/**
	 * @return void
	 */
	public function stop(): void
	{
		if ($this->client instanceof Bunny\Client) {
			$this->client->stop();

		} elseif ($this->client instanceof Bunny\Async\Client) {
			throw new Exceptions\InvalidStateException('Exchange have to be stopped via React/EventLoop service');
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
			$this->consumer->getQueueName(),
			false,
			true
		);

		// ...and bind it to the exchange
		foreach ($this->routingKeys as $routingKey) {
			$channel->queueBind(
				$this->consumer->getQueueName(),
				Constants::RABBIT_MQ_MESSAGE_BUS_EXCHANGE_NAME,
				$routingKey
			);
		}

		$channel->consume(
			function (Bunny\Message $message, Bunny\Channel $channel, Bunny\AbstractClient $client): void {
				$this->onBeforeConsumeMessage($message);

				$result = $this->consumer->consume($message);

				switch ($result) {
					case Consumers\IExchangeConsumer::MESSAGE_ACK:
						$channel->ack($message); // Acknowledge message
						break;

					case Consumers\IExchangeConsumer::MESSAGE_NACK:
						$channel->nack($message); // Message will be re-queued
						break;

					case Consumers\IExchangeConsumer::MESSAGE_REJECT:
						$channel->reject($message, false); // Message will be discarded
						break;

					case Consumers\IExchangeConsumer::MESSAGE_REJECT_AND_TERMINATE:
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
			$this->consumer->getQueueName()
		);
	}

}
