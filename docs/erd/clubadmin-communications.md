# ERD — ClubAdmin/Communications

```mermaid
erDiagram
    Communication {
        int id PK
        int author_id FK "nullable"
        string subject
        string body
        string reply_to "nullable"
        list<string> invitation_targets "nullable"
        int member_count
        int recipient_count
        datetime sent_at "nullable"
    }
    CommunicationRecipient {
        int id PK
        int communication_id FK
        string email
        list<int> user_ids
        string status
        string error "nullable"
        datetime sent_at "nullable"
    }

    Communication ||--o{ CommunicationRecipient : "recipients"
```
