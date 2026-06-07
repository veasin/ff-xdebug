<?php
include __DIR__ . '/vendor/autoload.php';

use function nx\{container, xdebug, route, hump, middleware, output, from, filter, input, cache, db, env, args, name, log, safe, hook};

xdebug();

// 容器操作
container('app.name', 'nx-tiny');
container('db.default', ['dsn' => 'mysql:host=localhost;dbname=test', 'username' => 'root']);
$name = container('app.name');

// 环境变量
env('APP_ENV');

// 命令行参数
args('-v --name=test');

// 命名配置
container('name', ['cache' => ['user' => 'cache:user:{uid}']]);
name('user', ['uid' => 123], 'cache');

// 数据验证
filter('123', 'int');
filter('hello@example.com', 'email');
filter('150', 'int,>100,<200');

// 输入获取
container('#in.params', ['id' => 123]);
from('id', 'params');
input('id', 'params', 'int', '>0');

// 路由注册
route('GET:/users', fn() => 'user list');
route('POST:/user', fn() => 'created');
route(['get:/api/list' => fn() => 'list', 'post:/api/create' => fn() => 'create']);
route('*', fn() => 'created');

// 日志
log('user login');
log('error occurred', 'error');

// 安全调用
safe(fn() => 2 + 2);

// 缓存
container('^config.cache', 'APCu');
if(!extension_loaded('apcu')) cache('APCu', fn() => 'cached-value');

// 钩子
hook(true);
hook('after', fn() => log('xxx'));
hook();
(fn() => 'created')();
xdebug(['\\']);

