import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\GameController::create
* @see app/Http/Controllers/GameController.php:81
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
* @see app/Http/Controllers/GameController.php:81
* @route '/api/game/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameController::create
* @see app/Http/Controllers/GameController.php:81
* @route '/api/game/create'
*/
create.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: create.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\GameController::join
* @see app/Http/Controllers/GameController.php:26
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
* @see app/Http/Controllers/GameController.php:26
* @route '/api/game/join'
*/
join.url = (options?: RouteQueryOptions) => {
    return join.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameController::join
* @see app/Http/Controllers/GameController.php:26
* @route '/api/game/join'
*/
join.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: join.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\GameController::shoot
* @see app/Http/Controllers/GameController.php:170
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
* @see app/Http/Controllers/GameController.php:170
* @route '/api/game/shoot'
*/
shoot.url = (options?: RouteQueryOptions) => {
    return shoot.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameController::shoot
* @see app/Http/Controllers/GameController.php:170
* @route '/api/game/shoot'
*/
shoot.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: shoot.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\GameController::placeShips
* @see app/Http/Controllers/GameController.php:111
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
* @see app/Http/Controllers/GameController.php:111
* @route '/api/game/place-ships'
*/
placeShips.url = (options?: RouteQueryOptions) => {
    return placeShips.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameController::placeShips
* @see app/Http/Controllers/GameController.php:111
* @route '/api/game/place-ships'
*/
placeShips.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: placeShips.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\GameController::useAbility
* @see app/Http/Controllers/GameController.php:218
* @route '/api/game/ability'
*/
export const useAbility = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: useAbility.url(options),
    method: 'post',
})

useAbility.definition = {
    methods: ["post"],
    url: '/api/game/ability',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\GameController::useAbility
* @see app/Http/Controllers/GameController.php:218
* @route '/api/game/ability'
*/
useAbility.url = (options?: RouteQueryOptions) => {
    return useAbility.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameController::useAbility
* @see app/Http/Controllers/GameController.php:218
* @route '/api/game/ability'
*/
useAbility.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: useAbility.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\GameController::randomPlacement
* @see app/Http/Controllers/GameController.php:389
* @route '/api/game/placement/random'
*/
export const randomPlacement = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: randomPlacement.url(options),
    method: 'post',
})

randomPlacement.definition = {
    methods: ["post"],
    url: '/api/game/placement/random',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\GameController::randomPlacement
* @see app/Http/Controllers/GameController.php:389
* @route '/api/game/placement/random'
*/
randomPlacement.url = (options?: RouteQueryOptions) => {
    return randomPlacement.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameController::randomPlacement
* @see app/Http/Controllers/GameController.php:389
* @route '/api/game/placement/random'
*/
randomPlacement.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: randomPlacement.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\GameController::rematch
* @see app/Http/Controllers/GameController.php:273
* @route '/api/game/rematch'
*/
export const rematch = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: rematch.url(options),
    method: 'post',
})

rematch.definition = {
    methods: ["post"],
    url: '/api/game/rematch',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\GameController::rematch
* @see app/Http/Controllers/GameController.php:273
* @route '/api/game/rematch'
*/
rematch.url = (options?: RouteQueryOptions) => {
    return rematch.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameController::rematch
* @see app/Http/Controllers/GameController.php:273
* @route '/api/game/rematch'
*/
rematch.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: rematch.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\GameController::state
* @see app/Http/Controllers/GameController.php:417
* @route '/api/game/state/{player}'
*/
export const state = (args: { player: number | { id: number } } | [player: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: state.url(args, options),
    method: 'get',
})

state.definition = {
    methods: ["get","head"],
    url: '/api/game/state/{player}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\GameController::state
* @see app/Http/Controllers/GameController.php:417
* @route '/api/game/state/{player}'
*/
state.url = (args: { player: number | { id: number } } | [player: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { player: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { player: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            player: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        player: typeof args.player === 'object'
        ? args.player.id
        : args.player,
    }

    return state.definition.url
            .replace('{player}', parsedArgs.player.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\GameController::state
* @see app/Http/Controllers/GameController.php:417
* @route '/api/game/state/{player}'
*/
state.get = (args: { player: number | { id: number } } | [player: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: state.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\GameController::state
* @see app/Http/Controllers/GameController.php:417
* @route '/api/game/state/{player}'
*/
state.head = (args: { player: number | { id: number } } | [player: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: state.url(args, options),
    method: 'head',
})

const GameController = { create, join, shoot, placeShips, useAbility, randomPlacement, rematch, state }

export default GameController