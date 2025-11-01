export type Dir = 'H' | 'V';

export type AuthUser = {
  id: number;
  name: string;
  email: string;
};

export type LeaderboardEntry = {
  user_id: number;
  name: string;
  ships_destroyed: number;
  ships_lost: number;
  wins: number;
  games: number;
  win_rate: number;
  shots_fired: number;
  hits: number;
  abilities_used: number;
};

export type ShipSpec = {
  name: string;
  size: number;
  count: number;
};

export type PlacedShip = {
  x: number;
  y: number;
  size: number;
  dir: Dir;
};

export type Step = 'join' | 'lobby' | 'placing' | 'playing';

// API DTOs (adjust to your backend if needed)
export type CreateGameResponse = {
  game_code: string;
  game_id: number;
  player_id: number;
};
export type JoinGameResponse = {
  game_id: number;
  player_id: number;
};
export type PlaceShipsResponse = {
  started: boolean;
};
export type ShootResponse = {
  result: 'hit' | 'miss' | 'sunk' | string;
};

export type LevelEdge = {
  id: number;
  name: string;
  min_points: number;
};

export type LevelInfo = {
  points: number;
  current: LevelEdge | null;
  next: (LevelEdge & { points_to_go: number }) | null;
};

export type AchievementProgress = {
  value: number;
  highest_step: number | null;
  next_step: number | null;
  remaining: number | null;
  completed: boolean;
  unlocked_at: string | null;
};

export type StepsLike = Map<number, number> | Record<string, number>;

export type AchievementItem = {
  key: string;
  name: string;
  description?: string | null;
  type: 'counter' | 'event';
  steps: StepsLike;
  progress: AchievementProgress;
  event_points: number;
};

export type RematchMapping = {
	old_player_id: number;
	new_player_id: number;
	name: string;
	user_id: number | null;
	is_turn: boolean;
};

export type RematchResponse = {
	status: 'waiting' | 'ready';
	message?: string;
	game?: { id: number; code: string };
	player?: RematchMapping;
	players?: RematchMapping[];
};

export type RematchEventPayload = {
	next?: { id: number; code: string };
	players?: RematchMapping[];
};

export type PublicGameSummary = {
	id: number;
	code: string;
	enemy_name: string;
};

export type BotTurnShot = {
	x: number;
	y: number;
	result: 'hit' | 'miss' | 'already' | 'sunk';
};

export type BotTurnAction = {
	type: 'shot' | 'ability';
	ability?: 'plane' | 'comb' | 'splatter' | null;
	shots: BotTurnShot[];
	sunk?: Array<{ size: number; cells: number[][] }>;
};

export type BotTurnPayload = {
	shots: BotTurnShot[];
	sunk?: Array<{ size: number; cells: number[][] }>;
	actions?: BotTurnAction[];
	gameOver: boolean;
	winner?: { id: number; name: string };
};
