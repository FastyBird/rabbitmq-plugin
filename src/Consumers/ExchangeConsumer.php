<?php declare(strict_types = 1);

/**
 * ExchangeConsumer.php
 *
 * @license        More in license.md
 * @copyright      https://fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:NodeExchange!
 * @subpackage     Consumers
 * @since          0.1.0
 *
 * @date           08.03.20
 */

namespace FastyBird\NodeExchange\Consumers;

use Bunny;
use FastyBird\NodeExchange\Exceptions;
use FastyBird\NodeMetadata\Schemas as NodeMetadataSchemas;
use Nette;
use Nette\Utils;
use Psr\Log;
use SplObjectStorage;
use Throwable;

/**
 * Exchange message consumer container
 *
 * @package        FastyBird:NodeExchange!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ExchangeConsumer implements IExchangeConsumer
{

	use Nette\SmartObject;

	/** @var string|null */
	private $queueName = null;

	/** @var SplObjectStorage */
	private $handlers;

	/** @var NodeMetadataSchemas\IValidator */
	private $jsonValidator;

	/** @var Log\LoggerInterface */
	private $logger;

	public function __construct(
		NodeMetadataSchemas\IValidator $jsonValidator,
		?Log\LoggerInterface $logger = null
	) {
		$this->jsonValidator = $jsonValidator;
		$this->logger = $logger ?? new Log\NullLogger();

		$this->handlers = new SplObjectStorage();
	}

	/**
	 * {@inheritDoc}
	 */
	public function setQueueName(string $queueName): void
	{
		$this->queueName = $queueName;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getQueueName(): string
	{
		if ($this->queueName === null) {
			throw new Exceptions\InvalidStateException('Name of the consumer queue is not set');
		}

		return $this->queueName;
	}

	/**
	 * {@inheritDoc}
	 */
	public function addHandler(IMessageHandler $handler): void
	{
		$this->handlers->attach($handler);
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasHandlers(): bool
	{
		return $this->handlers->count() > 0;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Exceptions\TerminateException
	 */
	public function consume(Bunny\Message $message): int
	{
		if (!$message->hasHeader('origin')) {
			return self::MESSAGE_REJECT;
		}

		// Message process result
		$result = false;

		/** @var IMessageHandler $handler */
		foreach ($this->handlers as $handler) {
			$schema = $handler->getSchema($message->routingKey, $message->getHeader('origin'));

			if ($schema !== null) {
				$payload = $this->validateMessage($message->content, $schema);

				if ($payload !== null) {
					$handlerResult = $this->processMessage($message, $payload, $handler);

					if ($handlerResult) {
						$result = $handlerResult;

					} else {
						$this->logger->debug('[FB:EXCHANGE] Received message could not be handled', [
							'message' => [
								'routingKey' => $message->routingKey,
								'headers'    => $message->headers,
								'body'       => $message->content,
							],
						]);
					}

				} else {
					$this->logger->debug('[FB:EXCHANGE] Received message is not valid', [
						'message' => [
							'routingKey' => $message->routingKey,
							'headers'    => $message->headers,
							'body'       => $message->content,
						],
					]);
				}
			}
		}

		if ($result) {
			return self::MESSAGE_ACK;
		}

		return self::MESSAGE_REJECT;
	}

	/**
	 * @param string $content
	 * @param string $schema
	 *
	 * @return Utils\ArrayHash|null
	 */
	private function validateMessage(
		string $content,
		string $schema
	): ?Utils\ArrayHash {
		try {
			return $this->jsonValidator->validate($content, $schema);

		} catch (Throwable $ex) {
			$this->logger->warning('[FB:EXCHANGE] Message validation error', [
				'exception' => [
					'message' => $ex->getMessage(),
					'code'    => $ex->getCode(),
				],
			]);

			return null;
		}
	}

	/**
	 * @param Bunny\Message $message
	 * @param Utils\ArrayHash $payload
	 * @param IMessageHandler $handler
	 *
	 * @return bool
	 *
	 * @throws Exceptions\TerminateException
	 */
	private function processMessage(
		Bunny\Message $message,
		Utils\ArrayHash $payload,
		IMessageHandler $handler
	): bool {
		try {
			return $handler->process($message->routingKey, $message->getHeader('origin'), $payload);

		} catch (Exceptions\TerminateException $ex) {
			throw $ex;

		} catch (Bunny\Exception\ClientException $ex) {
			throw new Exceptions\TerminateException($ex->getMessage(), $ex->getCode(), $ex);

		} catch (Throwable $ex) {
			return false;
		}
	}

}
