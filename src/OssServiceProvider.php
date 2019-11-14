<?php

namespace Chichumeng;

use Illuminate\Support\ServiceProvider;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use Storage;
class OssServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('aliyun-oss', function ($app, $config) {
            return new Filesystem(new AliyunAdapter($app, new Config($config)));
        });
    }
}
