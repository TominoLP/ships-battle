import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\GameController::create
* @see app/Http/Controllers/GameController.php:44
* @route '/api/game/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: create.url(options),
    method: 'post',
})

create.definition = {
    methods: ["post"],
    url: '/api/game/create',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\GameController::create
* @see app/Http/Controllers/GameController.php:44
* @route '/api/game/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameController::create
* @see app/Http/Controllers/GameController.php:44
* @route '/api/game/create'
*/
create.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: create.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\GameController::join
* @see app/Http/Controllers/GameController.php:14
* @route '/api/game/join'
*/
export const join = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: join.url(options),
    method: 'post',
})

join.definition = {
    methods: ["post"],
    url: '/api/game/join',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\GameController::join
* @see app/Http/Controllers/GameController.php:14
* @route '/api/game/join'
*/
join.url = (options?: RouteQueryOptions) => {
    return join.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameController::join
* @see app/Http/Controllers/GameController.php:14
* @route '/api/game/join'
*/
join.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: join.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\GameController::shoot
* @see app/Http/Controllers/GameController.php:157
* @route '/api/game/shoot'
*/
export const shoot = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: shoot.url(options),
    method: 'post',
})

shoot.definition = {
    methods: ["post"],
    url: '/api/game/shoot',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\GameController::shoot
* @see app/Http/Controllers/GameController.php:157
* @route '/api/game/shoot'
*/
shoot.url = (options?: RouteQueryOptions) => {
    return shoot.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameController::shoot
* @see app/Http/Controllers/GameController.php:157
* @route '/api/game/shoot'
*/
shoot.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: shoot.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\GameController::placeShips
* @see app/Http/Controllers/GameController.php:64
* @route '/api/game/place-ships'
*/
export const placeShips = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: placeShips.url(options),
    method: 'post',
})

placeShips.definition = {
    methods: ["post"],
    url: '/api/game/place-ships',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\GameController::placeShips
* @see app/Http/Controllers/GameController.php:64
* @route '/api/game/place-ships'
*/
placeShips.url = (options?: RouteQueryOptions) => {
    return placeShips.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameController::placeShips
* @see app/Http/Controllers/GameController.php:64
* @route '/api/game/place-ships'
*/
placeShips.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: placeShips.url(options),
    method: 'post',
})

const GameController = { create, join, shoot, placeShips }

export default GameController