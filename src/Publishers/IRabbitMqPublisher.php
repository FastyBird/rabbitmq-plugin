<?php declare(strict_types = 1);

/**
 * IRabbitMqPublisher.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:NodeExchange!
 * @subpackage     Publishers
 * @since          0.1.0
 *
 * @date           08.03.20
 */

namespace FastyBird\NodeExchange\Publishers;

/**
 * Rabbit MQ exchange publisher interface
 *
 * @package        FastyBird:NodeExchange!
 * @subpackage     Publishers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface IRabbitMqPublisher
{

	/**
	 * @param string $routingKey
	 * @param mixed[] $data
	 *
	 * @return void
	 */
	public function publish(string $routingKey, array $data): void;

}
