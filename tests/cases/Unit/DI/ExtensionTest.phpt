<?php declare(strict_types = 1);

namespace Tests\Cases;

use FastyBird\NodeExchange;
use FastyBird\NodeExchange\Connections;
use FastyBird\NodeExchange\Consumers;
use FastyBird\NodeExchange\Publishers;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../BaseTestCase.php';

/**
 * @testCase
 */
final class ExtensionTest extends BaseTestCase
{

	public function testServicesRegistration(): void
	{
		$container = $this->createContainer();

		Assert::notNull($container->getByType(NodeExchange\Exchange::class));

		Assert::notNull($container->getByType(Connections\IRabbitMqConnection::class));

		Assert::notNull($container->getByType(Consumers\IExchangeConsumer::class));

		Assert::notNull($container->getByType(Publishers\IRabbitMqPublisher::class));
	}

}

$test_case = new ServicesTest();
$test_case->run();
