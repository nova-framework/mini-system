<?php

namespace Mini\View;

use Mini\View\Compilers\MarkdownCompiler;
use Mini\View\Compilers\TemplateCompiler;
use Mini\View\Engines\CompilerEngine;
use Mini\View\Engines\EngineResolver;
use Mini\View\Engines\FileEngine;
use Mini\View\Engines\PhpEngine;
use Mini\View\Factory;
use Mini\View\FileViewFinder;
use Mini\View\Section;

use Mini\Support\ServiceProvider;


class ViewServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the Provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register the Service Provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerTemplateCompiler();

        $this->registerMarkdownCompiler();

        $this->registerEngineResolver();

        $this->registerViewFinder();

        $this->registerFactory();

        $this->registerSection();
    }

    /**
     * Register the Template compiler instance.
     *
     * @return void
     */
    public function registerTemplateCompiler()
    {
        $this->app->bindShared('template', function($app)
        {
            $cachePath = $app['config']['view.compiled'];

            return new TemplateCompiler($app['files'], $cachePath);
        });
    }

    /**
     * Register the Markdown compiler instance.
     *
     * @return void
     */
    public function registerMarkdownCompiler()
    {
        $this->app->bindShared('markdown', function($app)
        {
            $cachePath = $app['config']['view.compiled'];

            return new MarkdownCompiler($app['files'], $cachePath);
        });
    }

    /**
     * Register the Engine Resolver instance.
     *
     * @return void
     */
    public function registerEngineResolver()
    {
        $this->app->bindShared('view.engine.resolver', function($app)
        {
            $resolver = new EngineResolver();

            // Register the File Engine instance.
            $resolver->register('file', function()
            {
                return new FileEngine();
            });

            // Register the Default Engine instance.
            $resolver->register('php', function()
            {
                return new PhpEngine();
            });

            // Register the Template Engine instance.
            $resolver->register('template', function() use ($app)
            {
                return new CompilerEngine($app['template'], $app['files']);
            });

            // Register the Markdown Engine instance.
            $resolver->register('markdown', function() use ($app)
            {
                return new CompilerEngine($app['markdown'], $app['files']);
            });

            return $resolver;
        });
    }

    /**
     * Register the View Factory instance.
     *
     * @return void
     */
    public function registerFactory()
    {
        $this->app->bindShared('view', function($app)
        {
            $resolver = $app['view.engine.resolver'];

            $finder = $app['view.finder'];

            $factory = new Factory($resolver, $finder, $app['files']);

            $factory->share('app', $app);

            return $factory;
        });
    }

    /**
     * Register the view finder implementation.
     *
     * @return void
     */
    public function registerViewFinder()
    {
        $this->app->bindShared('view.finder', function($app)
        {
            $paths = $app['config']->get('view.paths', array());

            return new FileViewFinder($app['files'], $paths);
        });
    }

    /**
     * Register the View Factory instance.
     *
     * @return void
     */
    public function registerSection()
    {
        $this->app->bindShared('view.section', function($app)
        {
            return new Section($app['view']);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('view', 'view.engine.resolver', 'view.finder', 'template', 'markdown', 'section');
    }
}
