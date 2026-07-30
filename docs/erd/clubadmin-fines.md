# ERD — ClubAdmin/Fines

```mermaid
erDiagram
    Fine {
        int id PK
        int user_id FK
        int issued_by "nullable"
        float amount
        FineReason reason
        string federation_reference "nullable"
        string description "nullable"
        string pedagogical_message
    }

    Fine ||--o| Payment : "payment"
```
