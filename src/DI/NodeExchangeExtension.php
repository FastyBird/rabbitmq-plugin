<?php declare(strict_types = 1);

/**
 * NodeExchangeExtension.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:NodeExchange!
 * @subpackage     DI
 * @since          0.1.0
 *
 * @date           19.06.20
 */

namespace FastyBird\NodeExchange\DI;

use FastyBird\NodeExchange;
use FastyBird\NodeExchange\Connections;
use FastyBird\NodeExchange\Consumers;
use FastyBird\NodeExchange\Publishers;
use Nette;
use Nette\DI;
use Nette\Schema;
use stdClass;

/**
 * Microservice node helpers extension container
 *
 * @package        FastyBird:NodeExchange!
 * @subpackage     DI
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class NodeExchangeExtension extends DI\CompilerExtension
{

	/**
	 * {@inheritdoc}
	 */
	public function getConfigSchema(): Schema\Schema
	{
		return Schema\Expect::structure([
			'origin'   => Schema\Expect::string()->required(),
			'rabbitmq' => Schema\Expect::structure([
				'host'      => Schema\Expect::string()->default('127.0.0.1'),
				'port'      => Schema\Expect::int(5672),
				'vhost'     => Schema\Expect::string('/'),
				'username'  => Schema\Expect::string('guest'),
				'password'  => Schema\Expect::string('guest'),
				'queueName' => Schema\Expect::string()->required(),
				'routing'   => Schema\Expect::array()->items(Schema\Expect::string()),
			]),
		]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		/** @var stdClass $configuration */
		$configuration = $this->getConfig();

		$builder->addDefinition(null)
			->setType(Connections\RabbitMqConnection::class)
			->setArguments([
				'host'     => $configuration->rabbitmq->host,
				'port'     => $configuration->rabbitmq->port,
				'vhost'    => $configuration->rabbitmq->vhost,
				'username' => $configuration->rabbitmq->username,
				'password' => $configuration->rabbitmq->password,
			]);

		$builder->addDefinition(null)
			->setType(Consumers\ExchangeConsumer::class)
			->addSetup('?->setQueueName(?)', [
				'@self',
				$configuration->rabbitmq->queueName,
			]);

		$builder->addDefinition(null)
			->setType(Publishers\RabbitMqPublisher::class)
			->setArgument('origin', $configuration->origin);

		$builder->addDefinition(null)
			->setType(NodeExchange\Exchange::class)
			->setArgument('routingKeys', $configuration->rabbitmq->routing);
	}

	/**
	 * {@inheritdoc}
	 */
	public function beforeCompile(): void
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();

		/** @var string $messagesConsumerServiceName */
		$messagesConsumerServiceName = $builder->getByType(Consumers\ExchangeConsumer::class, true);

		/** @var DI\Definitions\ServiceDefinition $messagesConsumerService */
		$messagesConsumerService = $builder->getDefinition($messagesConsumerServiceName);

		$consumerHandlersServices = $builder->findByType(Consumers\IMessageHandler::class);

		foreach ($consumerHandlersServices as $consumerHandlersService) {
			$messagesConsumerService->addSetup('?->addHandler(?)', [
				'@self',
				$consumerHandlersService,
			]);
		}
	}

	/**
	 * @param Nette\Configurator $config
	 * @param string $extensionName
	 *
	 * @return void
	 */
	public static function register(
		Nette\Configurator $config,
		string $extensionName = 'nodeExchange'
	): void {
		$config->onCompile[] = function (
			Nette\Configurator $config,
			DI\Compiler $compiler
		) use ($extensionName): void {
			$compiler->addExtension($extensionName, new NodeExchangeExtension());
		};
	}

}
