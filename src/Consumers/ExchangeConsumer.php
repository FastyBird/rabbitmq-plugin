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
use FastyBird\ModulesMetadata\Exceptions as ModulesMetadataExceptions;
use FastyBird\ModulesMetadata\Loaders as ModulesMetadataLoaders;
use FastyBird\ModulesMetadata\Schemas as ModulesMetadataSchemas;
use FastyBird\RabbitMqPlugin\Exceptions;
use Nette;
use Nette\Utils;
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

	/** @var ModulesMetadataLoaders\ISchemaLoader */
	private ModulesMetadataLoaders\ISchemaLoader $schemaLoader;

	/** @var ModulesMetadataSchemas\IValidator */
	private ModulesMetadataSchemas\IValidator $validator;

	public function __construct(
		ModulesMetadataLoaders\ISchemaLoader $schemaLoader,
		ModulesMetadataSchemas\IValidator $validator
	) {
		$this->schemaLoader = $schemaLoader;
		$this->validator = $validator;

		$this->handlers = new SplObjectStorage();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getQueueName(): ?string
	{
		return $this->queueName;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setQueueName(?string $queueName): void
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
		if (!$message->hasHeader('origin') || !is_string($message->getHeader('origin'))) {
			return self::MESSAGE_REJECT;
		}

		$routingKey = $message->routingKey;
		$origin = $message->getHeader('origin');
		$payload = $message->content;

		try {
			$schema = $this->schemaLoader->load($origin, $routingKey);

		} catch (ModulesMetadataExceptions\InvalidArgumentException $ex) {
			return self::MESSAGE_REJECT;
		}

		try {
			$data = $this->validator->validate($payload, $schema);

		} catch (Throwable $ex) {
			return self::MESSAGE_REJECT;
		}

		// Message process result
		$result = false;

		/** @var IMessageHandler $handler */
		foreach ($this->handlers as $handler) {
			if ($this->processMessage($origin, $routingKey, $data, $handler)) {
				$result = true;
			}
		}

		if ($result) {
			return self::MESSAGE_ACK;
		}

		return self::MESSAGE_REJECT;
	}

	/**
	 * @param string $origin
	 * @param string $routingKey
	 * @param Utils\ArrayHash $data
	 * @param IMessageHandler $handler
	 *
	 * @return bool
	 *
	 * @throws Exceptions\TerminateException
	 */
	private function processMessage(
		string $origin,
		string $routingKey,
		Utils\ArrayHash $data,
		IMessageHandler $handler
	): bool {
		try {
			return $handler->process($origin, $routingKey, $data);

		} catch (Exceptions\TerminateException $ex) {
			throw $ex;

		} catch (Bunny\Exception\ClientException $ex) {
			throw new Exceptions\TerminateException($ex->getMessage(), $ex->getCode(), $ex);

		} catch (Throwable $ex) {
			return false;
		}
	}

}
