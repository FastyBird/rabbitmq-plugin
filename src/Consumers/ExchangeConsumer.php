<?php declare(strict_types = 1);

/**
 * ExchangeConsumer.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:RabbitMqPlugin!
 * @subpackage     Consumers
 * @since          0.1.0
 *
 * @date           08.03.20
 */

namespace FastyBird\RabbitMqPlugin\Consumers;

use Bunny;
use FastyBird\RabbitMqPlugin\Exceptions;
use Nette;
use SplObjectStorage;
use Throwable;

/**
 * Exchange message consumer container
 *
 * @package        FastyBird:RabbitMqPlugin!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ExchangeConsumer implements IExchangeConsumer
{

	use Nette\SmartObject;

	/** @var string|null */
	private ?string $queueName = null;

	/** @var SplObjectStorage */
	private SplObjectStorage $handlers;

	public function __construct()
	{
		$this->handlers = new SplObjectStorage();
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
	public function setQueueName(string $queueName): void
	{
		$this->queueName = $queueName;
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
			$result = $this->processMessage($message, $handler);
		}

		if ($result) {
			return self::MESSAGE_ACK;
		}

		return self::MESSAGE_REJECT;
	}

	/**
	 * @param Bunny\Message $message
	 * @param IMessageHandler $handler
	 *
	 * @return bool
	 *
	 * @throws Exceptions\TerminateException
	 */
	private function processMessage(
		Bunny\Message $message,
		IMessageHandler $handler
	): bool {
		try {
			return $handler->process($message->routingKey, $message->getHeader('origin'), $message->content);

		} catch (Exceptions\TerminateException $ex) {
			throw $ex;

		} catch (Bunny\Exception\ClientException $ex) {
			throw new Exceptions\TerminateException($ex->getMessage(), $ex->getCode(), $ex);

		} catch (Throwable $ex) {
			return false;
		}
	}

}
