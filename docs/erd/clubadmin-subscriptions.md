# ERD — ClubAdmin/Subscriptions

```mermaid
erDiagram
    Registration {
        int id PK
        int event_post_id FK
        int user_id FK
        float amount_due
        int amount_paid
        string status
    }
    Subscription {
        int id PK
        int season_id FK
        int user_id FK
        string status
        datetime confirmed_at "nullable"
        bool is_competitive
        bool has_other_family_members
        int trainings_count
        bool can_drive
        int seats_available "nullable"
        bool wants_to_be_captain
        bool volunteer_help
        bool wants_directed_training
        float subscription_price
        float training_unit_price
        float amount_due
        float amount_paid
        float family_credit
    }
    SubscriptionTrainingPack {
        int id PK
        int subscription_id FK
        int training_pack_id FK
        string status
        int waitlist_position "nullable"
        string confirmation_deadline "nullable"
        string starts_on "nullable"
        string ends_on "nullable"
        int|string override_amount "nullable"
        string override_reason "nullable"
    }

    Registration ||--o{ Payment : "payments"
    Subscription ||--o{ Payment : "payments"
    Subscription }o--o{ TrainingPack : "trainingPacks"
```
