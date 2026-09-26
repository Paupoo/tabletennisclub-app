# ERD — ClubAdmin/ExpenseReports

```mermaid
erDiagram
    ExpenseReport {
        int id PK
        int user_id FK
        ExpenseCategory category
        string description
        float amount
        float accepted_amount "nullable"
        datetime spent_on
        string refund_iban
        ExpenseReportStatus status
        string decision_reason "nullable"
        int decided_by "nullable"
        datetime decided_at "nullable"
        int resumed_from_id FK "nullable"
        datetime archived_at "nullable"
        datetime files_purged_at "nullable"
    }
    ExpenseReportExport {
        int id PK
        int requested_by
        string format
        list<int> report_ids
        string status
        string path "nullable"
        datetime expires_at "nullable"
    }
    ExpenseReportFile {
        int id PK
        int expense_report_id FK
        string path
        string original_name
        string mime_type
        int size
        string sha256
    }

    ExpenseReport ||--o{ ExpenseReportFile : "files"
    ExpenseReport ||--o{ Payment : "payments"
    ExpenseReport ||--o| Payment : "refund"
```
