<?php declare(strict_types = 1);

/**
 * IMessageHandler.php
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

use Nette\Utils;

/**
 * Exchange messages consumer interface
 *
 * @package        FastyBird:RabbitMqPlugin!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface IMessageHandler
{

	/**
	 * @param string $routingKey
	 * @param string $origin
	 * @param Utils\ArrayHash $data
	 *
	 * @return bool
	 */
	public function process(
		string $origin,
		string $routingKey,
		Utils\ArrayHash $data
	): bool;

}
