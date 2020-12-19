<?php declare(strict_types = 1);

/**
 * IExchangeConsumer.php
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

/**
 * Exchange messages consumer interface
 *
 * @package        FastyBird:RabbitMqPlugin!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface IExchangeConsumer
{

	public const MESSAGE_ACK = 1;
	public const MESSAGE_NACK = 2;
	public const MESSAGE_REJECT = 3;
	public const MESSAGE_REJECT_AND_TERMINATE = 4;

	/**
	 * @param string|null $queueName
	 *
	 * @return void
	 */
	public function setQueueName(?string $queueName): void;

	/**
	 * @return string|null
	 */
	public function getQueueName(): ?string;

	/**
	 * @param IMessageHandler $handler
	 *
	 * @return void
	 */
	public function addHandler(IMessageHandler $handler): void;

	/**
	 * @return bool
	 */
	public function hasHandlers(): bool;

	/**
	 * @param Bunny\Message $message
	 *
	 * @return int
	 */
	public function consume(Bunny\Message $message): int;

}
