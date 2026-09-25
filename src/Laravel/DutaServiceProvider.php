<?php

declare(strict_types=1);

namespace Duta\Laravel;

use Duta\Duta;
use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;

/**
 * Registered automatically by Laravel's package discovery. It binds the Duta
 * client and adds a `duta` mail transport, so MAIL_MAILER=duta sends every
 * Mailable through Duta with no other change.
 *
 * The key is `services.duta.key`, or DUTA_API_KEY when that is not set.
 */
class DutaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Duta::class, function ($app): Duta {
            $config = (array) $app['config']->get('services.duta', []);
            $options = [];
            if (!empty($config['base_url'])) {
                $options['base_url'] = (string) $config['base_url'];
            }
            return new Duta($config['key'] ?? (getenv('DUTA_API_KEY') ?: null), $options);
        });
        $this->app->alias(Duta::class, 'duta');
    }

    public function boot(): void
    {
        // A `duta` mailer, unless the application defined its own under that name.
        if (!$this->app['config']->has('mail.mailers.duta')) {
            $this->app['config']->set('mail.mailers.duta', ['transport' => 'duta']);
        }

        $this->app->afterResolving(MailManager::class, function (MailManager $manager): void {
            $manager->extend('duta', fn () => new DutaTransport($this->app->make(Duta::class)));
        });
    }
}
