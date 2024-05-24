<?php declare(strict_types = 1);

/**
 * Publisher.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:RabbitMqPlugin!
 * @subpackage     Publisher
 * @since          1.0.0
 *
 * @date           08.03.20
 */

namespace FastyBird\Plugin\RabbitMq\Publishers;

use Bunny;
use DateTimeInterface;
use FastyBird\DateTimeFactory;
use FastyBird\Library\Application\Helpers as ApplicationHelpers;
use FastyBird\Library\Exchange\Publisher as ExchangePublisher;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Plugin\RabbitMq\Channels;
use FastyBird\Plugin\RabbitMq\Utilities;
use Nette;
use Nette\Utils;
use Psr\Log;

/**
 * RabbitMQ exchange publisher
 *
 * @package        FastyBird:RabbitMqPlugin!
 * @subpackage     Publisher
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Publisher implements ExchangePublisher\Publisher
{

	use Nette\SmartObject;

	private Bunny\Channel|null $asyncChannel = null;

	public function __construct(
		private readonly string $exchangeName,
		private readonly Channels\Channel $channel,
		private readonly Utilities\IdentifierGenerator $identifier,
		private readonly DateTimeFactory\Factory $dateTimeFactory,
		private readonly Log\LoggerInterface $logger = new Log\NullLogger(),
	)
	{
	}

	/**
	 * @internal
	 */
	public function setChannel(Bunny\Channel $channel): void
	{
		$this->asyncChannel = $channel;
	}

	public function publish(
		MetadataTypes\Sources\Source $source,
		string $routingKey,
		MetadataDocuments\Document|null $entity,
	): bool
	{
		try {
			// Compose message
			$body = Utils\Json::encode($entity?->toArray());

		} catch (Utils\JsonException $ex) {
			$this->logger->error(
				'Data could not be converted to message',
				[
					'source' => MetadataTypes\Sources\Plugin::RABBITMQ->value,
					'type' => 'messages-publisher',
					'message' => [
						'routingKey' => $routingKey,
						'source' => $source->value,
						'data' => $entity?->toArray(),
					],
					'exception' => ApplicationHelpers\Logger::buildException($ex),
				],
			);

			return false;
		}

		$result = $this->getChannel()->publish(
			$body,
			[
				'sender_id' => $this->identifier->getIdentifier(),
				'source' => $source->value,
				'created' => $this->dateTimeFactory->getNow()->format(DateTimeInterface::ATOM),
			],
			$this->exchangeName,
			$routingKey,
		);

		if ($result === true) {
			$this->logger->debug(
				'Received message was pushed into data exchange',
				[
					'source' => MetadataTypes\Sources\Plugin::RABBITMQ->value,
					'type' => 'messages-publisher',
					'message' => [
						'routingKey' => $routingKey,
						'source' => $source->value,
						'data' => $entity?->toArray(),
						'body' => $body,
					],
				],
			);
		} else {
			$this->logger->error(
				'Received message could not be pushed into data exchange',
				[
					'source' => MetadataTypes\Sources\Plugin::RABBITMQ->value,
					'type' => 'messages-publisher',
					'message' => [
						'routingKey' => $routingKey,
						'source' => $source->value,
						'data' => $entity?->toArray(),
						'body' => $body,
					],
				],
			);
		}

		return true;
	}

	private function getChannel(): Channels\Channel|Bunny\Channel
	{
		if ($this->asyncChannel !== null) {
			return $this->asyncChannel;
		}

		return $this->channel;
	}

}
