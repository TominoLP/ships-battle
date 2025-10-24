import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \Illuminate\Routing\ViewController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/ViewController.php:32
* @route '/'
*/
const ViewController980bb49ee7ae63891f1d891d2fbcf1c9 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ViewController980bb49ee7ae63891f1d891d2fbcf1c9.url(options),
    method: 'get',
})

ViewController980bb49ee7ae63891f1d891d2fbcf1c9.definition = {
    methods: ["get","head"],
    url: '/',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Illuminate\Routing\ViewController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/ViewController.php:32
* @route '/'
*/
ViewController980bb49ee7ae63891f1d891d2fbcf1c9.url = (options?: RouteQueryOptions) => {
    return ViewController980bb49ee7ae63891f1d891d2fbcf1c9.definition.url + queryParams(options)
}

/**
* @see \Illuminate\Routing\ViewController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/ViewController.php:32
* @route '/'
*/
ViewController980bb49ee7ae63891f1d891d2fbcf1c9.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ViewController980bb49ee7ae63891f1d891d2fbcf1c9.url(options),
    method: 'get',
})

/**
* @see \Illuminate\Routing\ViewController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/ViewController.php:32
* @route '/'
*/
ViewController980bb49ee7ae63891f1d891d2fbcf1c9.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ViewController980bb49ee7ae63891f1d891d2fbcf1c9.url(options),
    method: 'head',
})

/**
* @see \Illuminate\Routing\ViewController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/ViewController.php:32
* @route '/game'
*/
const ViewController41256f21df62d96eb6bd2065c613d595 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ViewController41256f21df62d96eb6bd2065c613d595.url(options),
    method: 'get',
})

ViewController41256f21df62d96eb6bd2065c613d595.definition = {
    methods: ["get","head"],
    url: '/game',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Illuminate\Routing\ViewController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/ViewController.php:32
* @route '/game'
*/
ViewController41256f21df62d96eb6bd2065c613d595.url = (options?: RouteQueryOptions) => {
    return ViewController41256f21df62d96eb6bd2065c613d595.definition.url + queryParams(options)
}

/**
* @see \Illuminate\Routing\ViewController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/ViewController.php:32
* @route '/game'
*/
ViewController41256f21df62d96eb6bd2065c613d595.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ViewController41256f21df62d96eb6bd2065c613d595.url(options),
    method: 'get',
})

/**
* @see \Illuminate\Routing\ViewController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/ViewController.php:32
* @route '/game'
*/
ViewController41256f21df62d96eb6bd2065c613d595.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ViewController41256f21df62d96eb6bd2065c613d595.url(options),
    method: 'head',
})

const ViewController = {
    '/': ViewController980bb49ee7ae63891f1d891d2fbcf1c9,
    '/game': ViewController41256f21df62d96eb6bd2065c613d595,
}

export default ViewController