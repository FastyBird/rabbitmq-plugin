<?php declare(strict_types = 1);

/**
 * IMessageHandler.php
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

use Nette\Utils;

/**
 * Exchange messages consumer interface
 *
 * @package        FastyBird:NodeExchange!
 * @subpackage     Consumers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface IMessageHandler
{

	/**
	 * @param string $routingKey
	 * @param Utils\ArrayHash $payload
	 *
	 * @return bool
	 */
	public function process(
		string $routingKey,
		Utils\ArrayHash $payload
	): bool;

	/**
	 * @param string $routingKey
	 * @param string $origin
	 *
	 * @return string|null
	 */
	public function getSchema(string $routingKey, string $origin): ?string;

}
