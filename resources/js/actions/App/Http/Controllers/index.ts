import AuthController from './AuthController'
import GameController from './GameController'
import StatsController from './StatsController'

const Controllers = {
    AuthController: Object.assign(AuthController, AuthController),
    GameController: Object.assign(GameController, GameController),
    StatsController: Object.assign(StatsController, StatsController),
}

export default Controllers