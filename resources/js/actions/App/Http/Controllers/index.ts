import AuthController from './AuthController'
import GameController from './GameController'

const Controllers = {
    AuthController: Object.assign(AuthController, AuthController),
    GameController: Object.assign(GameController, GameController),
}

export default Controllers