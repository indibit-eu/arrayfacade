# ArrayFacade

Wraps PHP's built-in array functions, extends them and supports a functional, object-oriented style inspired by 
[Lodash](https://lodash.com/)

## Why another array wrapper?

- [Arrayy](https://github.com/voku/Arrayy) and [Arrayzy](https://github.com/bocharsky-bw/Arrayzy) lack `keyBy()`, `groupBy()`, `map()` and more
- [php-lodash](https://github.com/me-io/php-lodash) and [lodash-php](https://github.com/lodash-php/lodash-php) lack the object-oriented style

## Limitations

- `empty()`, `is_array()` and the `array_...()` functions cannot be called on instances of `ArrayFacade`
