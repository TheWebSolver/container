## Welcome

TheWebSolver Container is a PowerPack Dependency Injection and Auto-wiring solution that implements [PSR-11] Standards.

## Installation (via Composer)

Install library using composer command:

```sh
$ composer require thewebsolver/container
```

## Benefits

- **Contextual binding:** Provide abstract dependencies based on concrete entry.
- **[PSR-14 Events][PSR-14]:** Dispatch and Listen to events _before_, _during_ or _after_ resolving the entry.
- **Aliasing:** Container binding using an alias for a concrete classname, or even binding arbitrary value using lambda function.

## Usage

For usage details, visit [Wiki page][w].

[PSR-11]: https://www.php-fig.org/psr/psr-11/
[PSR-14]: https://www.php-fig.org/psr/psr-14/
[w]: https://github.com/TheWebSolver/container/wiki
