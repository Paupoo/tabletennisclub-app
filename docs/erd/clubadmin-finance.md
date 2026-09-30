# ERD — ClubAdmin/Finance

```mermaid
erDiagram
    FinancialExport {
        int id PK
        int requested_by
        string format
        int fiscal_year "nullable"
        string poste "nullable"
        FinancialExportScope scope
        list<int> report_ids
        string status
        string path "nullable"
        datetime expires_at "nullable"
    }

```
