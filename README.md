# Perks n' Perils: Hands of Chaos

## Developer
* Christian Johnston

## Project Overview
Perks n' Perils is a PHP-powered multiplayer party game that turns strategic drafting into a social debate. You’re dropped into a random situation and given a choice: pick your character and a special perk to help you survive. But there’s a catch—at the moment of truth, everyone gets hit with a random Peril that could ruin everything.
Once the cards are on the table, the game is out of the computer's hands and in yours. You'll have to argue, plead, and get creative to convince the room that your character is the best fit for the job. In this game, it’s not about who has the best stats—it’s about who gives the best pitch.

## Architecture
* **Backend:** PHP (Server-side session management and game state logic).
* **Database:** PostgreSQL (Storage for character bios, perk/peril metadata, and global situations).
* **Frontend:** HTML5 and CSS3.
* **Networking:** Secure Join-Code lobby system for private session management.

## Game Mechanics & Logic

### 1. The Drafting Phase
* **Randomized Selection:** Players are presented with a situation and three random characters/perks fetched from the PostgreSQL database.
* **Risk-Reward Point System:** Implemented a balancing algorithm where Perk power levels are inversely proportional to their point value. Choosing an "overpowered" perk yields fewer points, forcing players to get creative with their arguments to get an advantage.

### 2. The Debate & Voting Phase
* **Dynamic Card Generation:** The system displays each player's unique combination (Character + Perk + Random Peril) alongside the round's situation.
* **Majority Voting Logic:** Developed a voting resolution engine that calculates majority wins, handles tie-breaking (nullifying points for the round), and updates the persistent leaderboard.

### 3. Session & Lobby Management
* **Join-Code Infrastructure:** Engineered a lobby system using PHP sessions and unique alphanumeric codes, allowing for isolated game instances without account requirements.
* **Game State Sync:** Ensured that the transition from drafting to voting is synchronized across all clients within the same lobby ID.

## My Technical Contributions
* **Backend Architecture:** Developed the full PHP engine responsible for game flow, player distribution, and round transitions.
* **Relational Database Design:** Architected the PostgreSQL schema to manage complex relationships between Situations, Characters, and the weighted point values of Perks.
* **UI/UX Framework:** Designed an intuitive interface, ensuring that the character cards and debate prompts are the focal point of the user experience.
* **Reliability Engineering:** Integrated strict validation to prevent double-voting and ensures that lobby codes remain unique and secure during active sessions.
* **Asset Governance:** Implemented a permanent asset delivery pipeline for character images to ensure zero-latency loading during critical voting phases.

## AI Tools
* **ChatGPT & Gemini:** Leveraged for brainstorming balanced Peril debuffs and optimizing SQL queries for randomizing card draws from the database.