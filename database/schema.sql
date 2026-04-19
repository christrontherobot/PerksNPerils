-- Core Game Sessions
CREATE TABLE lobbies (
    id SERIAL PRIMARY KEY,
    join_code VARCHAR(6) UNIQUE,   -- Alphanumeric session key
    status TEXT DEFAULT 'waiting', -- waiting, picking, voting, result
    current_situation_id INTEGER REFERENCES situations(id)
);

-- Player State & Drafting
CREATE TABLE players (
    id SERIAL PRIMARY KEY,
    lobby_id INTEGER REFERENCES lobbies(id),
    username VARCHAR(50),
    score INTEGER DEFAULT 0,
    char_id INTEGER REFERENCES characters(id),
    strength_id INTEGER REFERENCES strengths(id),
    weakness_id INTEGER REFERENCES weaknesses(id),
    has_submitted BOOLEAN DEFAULT false,
    voted_for_id INTEGER REFERENCES players(id) -- Tracks votes for the 'Source of Truth'
);

-- Content Metadata
CREATE TABLE situations (id SERIAL PRIMARY KEY, description TEXT);
CREATE TABLE characters (id SERIAL PRIMARY KEY, description TEXT, image_url TEXT);
CREATE TABLE strengths  (id SERIAL PRIMARY KEY, description TEXT, points INTEGER);
CREATE TABLE weaknesses (id SERIAL PRIMARY KEY, description TEXT);