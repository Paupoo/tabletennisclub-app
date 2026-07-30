# ERD — Competitions/Tournament

```mermaid
erDiagram
    MatchSet {
        int id PK
        int tournament_match_id FK "nullable"
        int set_number
        int player1_score
        int player2_score
        int winner_id FK "nullable"
    }
    Pool {
        int id PK
        string name
        int tournament_id FK
    }
    TableTournament {
        int id PK
        int tournament_id FK "nullable"
        int table_id FK "nullable"
        int tournament_match_id FK "nullable"
        int is_table_free
        datetime match_started_at "nullable"
        string match_ended_at "nullable"
    }
    Tournament {
        int id PK
        string name
        datetime start_date "nullable"
        string start_time "nullable"
        datetime registration_deadline "nullable"
        datetime end_date "nullable"
        int total_users
        int max_users
        mixed price
        TournamentStatusEnum status
        bool has_handicap_points
        int duration_minutes
        int pool_size
        int nb_pools
        int nb_qualifiers_per_pool
        int sets_to_win
        int logistics_buffer_minutes
        string match_type
        string description "nullable"
        string location "nullable"
        string image "nullable"
        int news_post_id FK "nullable"
        TournamentObjectiveEnum objective "nullable"
        bool deuce_enabled
        string doubles_registration_mode "nullable"
        mixed 0
    }
    TournamentMatch {
        int id PK
        int pool_id FK "nullable"
        int tournament_id FK "nullable"
        int table_id FK "nullable"
        int player1_id FK "nullable"
        int player1_handicap_points
        int player2_id FK "nullable"
        int player2_handicap_points
        int winner_id FK "nullable"
        string round "nullable"
        string status
        string started_ad "nullable"
        int match_order
        datetime scheduled_time "nullable"
        int table_number "nullable"
        int next_match_id FK "nullable"
        int bronze_match_id FK "nullable"
        int is_bronze_match
        int pair1_id FK "nullable"
        int pair2_id FK "nullable"
        int referee_id FK "nullable"
        bool is_forfeit
    }
    TournamentPair {
        int id PK
        int tournament_id FK
        int player1_id FK
        int player2_id FK
        int registered_by
    }
    TournamentRegistration {
        int id PK
        int user_id FK "nullable"
        int tournament_id FK "nullable"
        bool has_paid
        string registration_status
        int waitlist_position "nullable"
        datetime confirmation_deadline "nullable"
        datetime payment_deadline "nullable"
        int payment_id FK "nullable"
        bool qr_confirmed
    }

    Pool }o--o{ TournamentPair : "pairs"
    Pool ||--o{ TournamentMatch : "tournamentmatches"
    Pool }o--o{ User : "users"
    Tournament ||--o| EventPost : "eventPost"
    Tournament ||--o{ TournamentMatch : "matches"
    Tournament ||--o{ TournamentPair : "pairs"
    Tournament ||--o{ Pool : "pools"
    Tournament }o--o{ Room : "rooms"
    Tournament }o--o{ Table : "tables"
    Tournament }o--o{ User : "users"
    TournamentMatch ||--o{ MatchSet : "sets"
    TournamentMatch }o--o{ Table : "table"
    TournamentRegistration ||--o| Payment : "payment"
```
