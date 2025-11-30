/*
  # Multiplayer Tic-Tac-Toe Rooms System

  1. New Tables
    - `rooms`
      - `id` (text, primary key) - 5 character room ID
      - `password` (text, nullable) - Optional room password
      - `creator_symbol` (text) - X or O assigned to creator
      - `opponent_symbol` (text) - X or O assigned to opponent
      - `creator_connected` (boolean) - Creator connection status
      - `opponent_connected` (boolean) - Opponent connection status
      - `current_turn` (text) - Current player's symbol (X or O)
      - `board_state` (jsonb) - Game board state (9 cells)
      - `winner` (text, nullable) - Winner symbol or 'draw'
      - `created_at` (timestamptz) - Room creation time
      - `last_activity` (timestamptz) - Last activity timestamp

  2. Security
    - Enable RLS on `rooms` table
    - Add policies for public read/write access (no auth required for this game)
    
  3. Important Notes
    - Rooms are identified by a 5-character alphanumeric ID
    - Board state stores all 9 cells with their status
    - Real-time subscriptions will be used for live gameplay
*/

CREATE TABLE IF NOT EXISTS rooms (
  id text PRIMARY KEY,
  password text,
  creator_symbol text NOT NULL DEFAULT 'X',
  opponent_symbol text NOT NULL DEFAULT 'O',
  creator_connected boolean DEFAULT true,
  opponent_connected boolean DEFAULT false,
  current_turn text NOT NULL DEFAULT 'X',
  board_state jsonb DEFAULT '[
    {"index": 0, "value": "", "player": ""},
    {"index": 1, "value": "", "player": ""},
    {"index": 2, "value": "", "player": ""},
    {"index": 3, "value": "", "player": ""},
    {"index": 4, "value": "", "player": ""},
    {"index": 5, "value": "", "player": ""},
    {"index": 6, "value": "", "player": ""},
    {"index": 7, "value": "", "player": ""},
    {"index": 8, "value": "", "player": ""}
  ]'::jsonb,
  winner text,
  created_at timestamptz DEFAULT now(),
  last_activity timestamptz DEFAULT now()
);

ALTER TABLE rooms ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Anyone can view rooms"
  ON rooms
  FOR SELECT
  USING (true);

CREATE POLICY "Anyone can create rooms"
  ON rooms
  FOR INSERT
  WITH CHECK (true);

CREATE POLICY "Anyone can update rooms"
  ON rooms
  FOR UPDATE
  USING (true)
  WITH CHECK (true);

CREATE POLICY "Anyone can delete rooms"
  ON rooms
  FOR DELETE
  USING (true);

-- Create index for faster lookups
CREATE INDEX IF NOT EXISTS idx_rooms_last_activity ON rooms(last_activity);
CREATE INDEX IF NOT EXISTS idx_rooms_created_at ON rooms(created_at);