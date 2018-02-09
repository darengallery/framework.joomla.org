<?php
/**
 * Joomla! Framework Website
 *
 * @copyright  Copyright (C) 2014 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

namespace Joomla\FrameworkWebsite\Service;

use DebugBar\
{
	DebugBar, StandardDebugBar
};
use DebugBar\Bridge\{
	MonologCollector, TwigProfileCollector
};
use DebugBar\Bridge\Twig\TimeableTwigExtensionProfiler;
use DebugBar\DataCollector\PDO\{
	PDOCollector, TraceablePDO
};
use Joomla\Application\AbstractWebApplication;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\
{
	Container, Exception\DependencyResolutionException, ServiceProviderInterface
};
use Joomla\FrameworkWebsite\DebugBar\JoomlaHttpDriver;

/**
 * Debug bar service provider
 */
class DebugBarProvider implements ServiceProviderInterface
{
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 */
	public function register(Container $container)
	{
		$container->alias(DebugBar::class, 'debug.bar')
			->alias(StandardDebugBar::class, 'debug.bar')
			->share('debug.bar', [$this, 'getDebugBarService'], true);

		$container->alias(MonologCollector::class, 'debug.collector.monolog')
			->share('debug.collector.monolog', [$this, 'getDebugCollectorMonologService'], true);

		$container->alias(PDOCollector::class, 'debug.collector.pdo')
			->share('debug.collector.pdo', [$this, 'getDebugCollectorPdoService'], true);

		$container->alias(TwigProfileCollector::class, 'debug.collector.twig')
			->share('debug.collector.twig', [$this, 'getDebugCollectorTwigService'], true);

		$container->alias(JoomlaHttpDriver::class, 'debug.http.driver')
			->share('debug.http.driver', [$this, 'getDebugHttpDriverService'], true);

		$container->extend('twig.extension.profiler', [$this, 'getDecoratedTwigExtensionProfilerService']);

		$this->tagTwigExtensions($container);
	}

	/**
	 * Get the `debug.bar` service
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  DebugBar
	 */
	public function getDebugBarService(Container $container) : DebugBar
	{
		if (!class_exists(StandardDebugBar::class))
		{
			throw new DependencyResolutionException(sprintf('The %s class is not loaded.', StandardDebugBar::class));
		}

		$debugBar = new StandardDebugBar;

		// Add collectors
		$debugBar->addCollector($container->get('debug.collector.monolog'));
		$debugBar->addCollector($container->get('debug.collector.pdo'));
		$debugBar->addCollector($container->get('debug.collector.twig'));

		// Ensure the assets are dumped
		$renderer = $debugBar->getJavascriptRenderer();
		$renderer->dumpCssAssets(JPATH_ROOT . '/www/media/css/debugbar.css');
		$renderer->dumpJsAssets(JPATH_ROOT . '/www/media/js/debugbar.js');

		return $debugBar;
	}

	/**
	 * Get the `debug.collector.monolog` service
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  MonologCollector
	 */
	public function getDebugCollectorMonologService(Container $container) : MonologCollector
	{
		$collector = new MonologCollector;
		$collector->addLogger($container->get('monolog.logger.application.web'));

		return $collector;
	}

	/**
	 * Get the `debug.collector.pdo` service
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  PDOCollector
	 */
	public function getDebugCollectorPdoService(Container $container) : PDOCollector
	{
		/** @var DatabaseInterface $db */
		$db = $container->get('db');
		$db->connect();

		return new PDOCollector(new TraceablePDO($db->getConnection()));
	}

	/**
	 * Get the `debug.collector.twig` service
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  TwigProfileCollector
	 */
	public function getDebugCollectorTwigService(Container $container) : TwigProfileCollector
	{
		return new TwigProfileCollector($container->get('twig.profiler.profile'), $container->get('twig.loader'));
	}

	/**
	 * Get the `debug.http.driver` service
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  JoomlaHttpDriver
	 */
	public function getDebugHttpDriverService(Container $container): JoomlaHttpDriver
	{
		return new JoomlaHttpDriver($container->get(AbstractWebApplication::class));
	}

	/**
	 * Get the decorated `twig.extension.profiler` service
	 *
	 * @param   \Twig_Extension_Profiler  $profiler   The original \Twig_Extension_Profiler service.
	 * @param   Container                 $container  The DI container.
	 *
	 * @return  TimeableTwigExtensionProfiler
	 */
	public function getDecoratedTwigExtensionProfilerService(\Twig_Extension_Profiler $profiler, Container $container): TimeableTwigExtensionProfiler
	{
		return new TimeableTwigExtensionProfiler($container->get('twig.profiler.profile'), $container->get('debug.bar')['time']);
	}

	/**
	 * Tag services which are Twig extensions
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 */
	private function tagTwigExtensions(Container $container)
	{
		$container->tag('twig.extension', ['twig.extension.profiler']);
	}
}
