<?php declare(strict_types = 1);

/**
 * RabbitMqPublisher.php
 *
 * @license        More in license.md
 * @copyright      https://fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:NodeExchange!
 * @subpackage     Publishers
 * @since          0.1.0
 *
 * @date           08.03.20
 */

namespace FastyBird\NodeExchange\Publishers;

use FastyBird\DateTimeFactory;
use FastyBird\NodeExchange;
use FastyBird\NodeExchange\Connections;
use Nette;
use Nette\Utils;
use Psr\Log;
use React\Promise;

/**
 * RabbitMQ exchange publisher
 *
 * @package        FastyBird:NodeExchange!
 * @subpackage     Publishers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class RabbitMqPublisher implements IRabbitMqPublisher
{

	use Nette\SmartObject;

	/** @var string */
	private $origin;

	/** @var Connections\IRabbitMqConnection */
	private $connection;

	/** @var DateTimeFactory\DateTimeFactory */
	private $dateTimeFactory;

	/** @var Log\LoggerInterface */
	private $logger;

	public function __construct(
		string $origin,
		Connections\IRabbitMqConnection $connection,
		DateTimeFactory\DateTimeFactory $dateTimeFactory,
		?Log\LoggerInterface $logger = null
	) {
		$this->origin = $origin;

		$this->connection = $connection;
		$this->dateTimeFactory = $dateTimeFactory;
		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * {@inheritDoc}
	 */
	public function publish(
		string $routingKey,
		array $data
	): void {
		try {
			// Compose message
			$message = Utils\Json::encode($data);

		} catch (Utils\JsonException $ex) {
			$this->logger->error('[FB:EXCHANGE] Data could not be converted to message', [
				'exception' => [
					'message' => $ex->getMessage(),
					'code'    => $ex->getCode(),
				],
			]);

			return;
		}

		$result = $this->connection->getChannel()
			->publish(
				$message,
				[
					'origin'  => $this->origin,
					'created' => $this->dateTimeFactory->getNow()->format(DATE_ATOM),
				],
				NodeExchange\Constants::RABBIT_MQ_MESSAGE_BUS_EXCHANGE_NAME,
				$routingKey
			);

		if (is_bool($result)) {
			if ($result) {
				$this->logger->info('[FB:EXCHANGE] Received message was pushed into data exchange');
			} else {
				$this->logger->error('[FB:EXCHANGE] Received message could not be pushed into data exchange');
			}

		} elseif ($result instanceof Promise\PromiseInterface) {
			$result
				->then(
					function (): void {
						$this->logger->info('[FB:EXCHANGE] Received message was pushed into data exchange');
					},
					function (): void {
						$this->logger->error('[FB:EXCHANGE] Received message could not be pushed into data exchange');
					}
				);
		}
	}

}
