<?php declare(strict_types = 1);

/**
 * InvalidStateException.php
 *
 * @license        More in license.md
 * @copyright      https://fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:NodeExchange!
 * @subpackage     Exceptions
 * @since          0.1.0
 *
 * @date           08.03.20
 */

namespace FastyBird\NodeExchange\Exceptions;

use RuntimeException;

class InvalidStateException extends RuntimeException implements IException
{

}
