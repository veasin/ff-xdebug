# ff-xdebug

[ff](https://github.com/veasin/ff) 框架的 Xdebug 追踪查看器。提供单一函数即可启动/停止 Xdebug 追踪，并以格式化表格展示函数调用时间线，支持耗时统计、过滤筛选和源码定位。

## 安装

```bash
composer require veasin/ff-xdebug
```

## 用法

```php
use function ff\xdebug;

// 开始追踪
xdebug();

// ... 你的代码 ...

// 停止追踪，返回原始数据数组（不输出表格）
xdebug(false);
```

停止追踪并显示结果：

```php
xdebug([]); // 空数组 = 不过滤，显示所有调用
```

## 输出

终端中生成格式化表格：

```
  Xdebug Trace
  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   +ms           call                     caller
  ─────────────────────────────────────────
   0.000         container('app.name')     /demo/ff.php:9
   0.042         container('db.default')   /demo/ff.php:10
   ...
  ─────────────────────────────────────────
  Total: 32 calls, 15.234 ms
```

## 过滤

向 `xdebug()` 传入过滤规则数组，仅显示符合条件的调用：

```php
xdebug(['container', 'route', '\\']);
```

| 过滤规则         | 说明                 |
|--------------|--------------------|
| `'funcName'` | 仅显示指定函数名的调用        |
| `'\\'`       | 仅显示非用户定义的内部 PHP 函数 |
| `'{}'`       | 仅显示闭包调用            |
| `'!prefix'`  | 排除以指定前缀开头的函数       |
| `'ff+'`      | 显示 ff 框架内部调用（默认隐藏） |

## API

### `ff\xdebug(null|bool|array $enable = null): mixed`

| 参数 | 行为 |
|------|------|
| `null` 或 `true` | 开始追踪（重置之前的追踪） |
| `false` | 停止追踪，返回原始调用数据数组（不输出表格） |
| `array` | 停止追踪，按过滤规则显示结果 |
| 无参数（后续调用） | 重新开始追踪（不会停止/显示） |

开始追踪返回 `null`；传入数组时输出表格并返回 `null`；`$enable === false` 时返回调用数据数组（不输出表格）。

### 调用数据结构

```php
[
  'level'      => int,    // 调用栈深度
  'func_name'  => string, // 完整函数名
  'display_name' => string, // 简短名称（去除 ff\ 前缀）
  'file'       => string, // 源文件路径
  'line'       => string, // 源码行号
  'args'       => string, // 参数字符串
  'elapsed'    => float,  // 距离首次调用的毫秒数
  'duration'   => float,  // 函数执行耗时（毫秒）
  'is_user'    => int,    // 是否为用户定义函数
]
```

## 运行环境

- PHP ^8.5
- 需启用 [Xdebug](https://xdebug.org/) 扩展
- [ff](https://github.com/veasin/ff) >=0.2.0

## 许可证

LGPL-3.0-or-later
