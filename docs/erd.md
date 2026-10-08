# Entity-Relationship Diagram — Vue globale

```mermaid
erDiagram
    %% Bar
    BarCategory
    BarInventory
    BarInventoryLine
    BarOrder
    BarOrderItem
    BarPayment
    BarProduct
    BarRestocking
    BarRestockingAdjustment
    BarRestockingLine
    BarStockMovement

    %% ClubAdmin/Club
    KeyRing
    Room
    Table

    %% ClubAdmin/Communications
    Communication
    CommunicationRecipient

    %% ClubAdmin/Contact
    Contact
    EmailTemplate

    %% ClubAdmin/ExpenseReports
    ExpenseReport
    ExpenseReportFile

    %% ClubAdmin/Feedback
    FeedbackCampaign
    FeedbackCampaignResponse
    FeedbackEntry
    FeedbackTheme
    HelpOffer
    HelpTask

    %% ClubAdmin/Finance
    FinancialExport

    %% ClubAdmin/Fines
    Fine

    %% ClubAdmin/Payment
    BankAccount
    BankImport
    CashRegister
    CashRegisterEntry
    Payment
    PaymentCredit
    Transaction

    %% ClubAdmin/Subscriptions
    Registration
    Subscription
    SubscriptionDiscount
    SubscriptionTrainingPack

    %% ClubAdmin/Subscriptions/Attestations
    AttestationSetting
    AttestationTemplate
    MutualAttestation

    %% ClubAdmin/SupportingDocuments
    SupportingDocument
    SupportingDocumentFile

    %% ClubAdmin/Users
    CharterSignature
    FamilyGroup
    Guardian
    MemberDeparture
    MemberImport
    User

    %% ClubPosts
    EventPost
    NewsPost

    %% Competitions/Interclub
    Club
    Interclub
    InterclubChange
    InterclubImport
    InterclubIndividualMatch
    InterclubResult
    League
    OfficialTournamentMatch
    Season
    Team
    TeamUser

    %% Competitions/Tournament
    MatchSet
    Pool
    TableTournament
    Tournament
    TournamentMatch
    TournamentPair
    TournamentRegistration

    %% Meetings
    Meeting
    MeetingActionItem
    MeetingAgendaItem
    MeetingDateProposal
    MeetingDateVote
    MeetingDecision
    MeetingMinutes
    MeetingUser

    %% Shared
    AppSetting

    %% Trainings
    Training
    TrainingLevel
    TrainingPack
    TrainingPlan
    TrainingPlanAssignment
    TrainingPlanPack

    BarCategory ||--o{ BarProduct : "products"
    BarInventory ||--o{ BarInventoryLine : "lines"
    BarOrder ||--o{ BarOrderItem : "items"
    BarOrder ||--o| Payment : "payment"
    BarProduct ||--o{ BarStockMovement : "stockMovements"
    BarRestocking ||--o{ BarRestockingLine : "lines"
    Room }o--o{ Club : "clubs"
    Room ||--o{ Interclub : "interclubs"
    Room ||--o{ Table : "tables"
    Room }o--o{ Tournament : "tournaments"
    Room ||--o{ TrainingPack : "trainingPacks"
    Room ||--o{ Training : "trainings"
    Table }o--o{ TournamentMatch : "match"
    Table }o--o{ Tournament : "tournaments"
    Communication ||--o{ CommunicationRecipient : "recipients"
    ExpenseReport ||--o{ ExpenseReportFile : "files"
    ExpenseReport ||--o{ Payment : "payments"
    ExpenseReport ||--o| Payment : "refund"
    ExpenseReport ||--o| BarRestocking : "restocking"
    FeedbackCampaign }o--o{ User : "participants"
    FeedbackCampaign ||--o{ FeedbackCampaignResponse : "responses"
    FeedbackCampaignResponse ||--o{ FeedbackEntry : "comments"
    FeedbackTheme ||--o{ FeedbackEntry : "entries"
    HelpOffer }o--o{ HelpTask : "tasks"
    HelpTask }o--o{ HelpOffer : "offers"
    Fine ||--o| Payment : "payment"
    BankAccount ||--o{ Transaction : "transactions"
    BankImport ||--o{ Transaction : "transactions"
    CashRegister ||--o{ CashRegisterEntry : "entries"
    CashRegisterEntry }o--o{ SupportingDocument : "supportingDocuments"
    Payment ||--o{ PaymentCredit : "credits"
    Payment ||--o{ SubscriptionDiscount : "discounts"
    Transaction ||--o| CashRegisterEntry : "cashRegisterEntry"
    Transaction ||--o{ PaymentCredit : "credits"
    Transaction ||--o| Payment : "payment"
    Transaction ||--o| Payment : "refundPayment"
    Transaction }o--o{ SupportingDocument : "supportingDocuments"
    Registration ||--o{ Payment : "payments"
    Subscription ||--o{ SubscriptionDiscount : "discounts"
    Subscription ||--o{ Payment : "payments"
    Subscription }o--o{ TrainingPack : "trainingPacks"
    SubscriptionTrainingPack ||--o{ Payment : "payments"
    SubscriptionTrainingPack ||--o| User : "user"
    SupportingDocument }o--o{ CashRegisterEntry : "cashRegisterEntries"
    SupportingDocument ||--o{ SupportingDocumentFile : "files"
    SupportingDocument }o--o{ Transaction : "transactions"
    FamilyGroup }o--o{ User : "users"
    Guardian }o--o{ User : "users"
    MemberImport ||--o{ User : "members"
    User ||--o{ NewsPost : "articles"
    User ||--o| Team : "captainOf"
    User ||--o{ CharterSignature : "charterSignatures"
    User ||--o| TrainingPack : "coachOf"
    User ||--o| Training : "coachOfSession"
    User ||--o{ MemberDeparture : "departures"
    User ||--o| MemberDeparture : "departureThisSeason"
    User }o--o{ FamilyGroup : "familyGroups"
    User ||--o| Guardian : "guardianRecord"
    User }o--o{ FeedbackCampaign : "feedbackCampaigns"
    User }o--o{ Guardian : "guardians"
    User ||--o{ CashRegister : "heldCashRegisters"
    User }o--o{ Interclub : "interclubs"
    User ||--o{ KeyRing : "keyRings"
    User }o--o{ Meeting : "meetings"
    User ||--o{ OfficialTournamentMatch : "officialTournamentMatches"
    User }o--o{ Pool : "pools"
    User }o--o{ Season : "seasons"
    User ||--o{ Subscription : "subscriptions"
    User }o--o{ Team : "teams"
    User }o--o{ Tournament : "tournaments"
    User }o--o{ Training : "trainings"
    Club }o--o{ Room : "rooms"
    Club ||--o{ Team : "teams"
    Club ||--o{ User : "users"
    Interclub ||--o{ InterclubIndividualMatch : "individualMatches"
    Interclub ||--o| InterclubResult : "interclubResult"
    Interclub ||--o{ Team : "teams"
    Interclub }o--o{ User : "users"
    League ||--o{ Interclub : "interclubs"
    League ||--o{ Team : "teams"
    Season ||--o{ InterclubResult : "interclubResults"
    Season ||--o{ Interclub : "interclubs"
    Season ||--o{ League : "leagues"
    Season ||--o{ Subscription : "subscriptions"
    Season ||--o{ Team : "teams"
    Season ||--o{ TrainingPack : "trainingPacks"
    Season ||--o{ Training : "trainings"
    Season }o--o{ User : "users"
    Team ||--o{ InterclubResult : "interclubResults"
    Team ||--o{ Interclub : "interclubs"
    Team }o--o{ User : "users"
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
    Meeting ||--o{ MeetingActionItem : "actionItems"
    Meeting ||--o{ MeetingAgendaItem : "agendaItems"
    Meeting ||--o{ MeetingDateProposal : "dateProposals"
    Meeting ||--o{ MeetingDecision : "decisions"
    Meeting ||--o| EventPost : "eventPost"
    Meeting ||--o| MeetingMinutes : "minutes"
    Meeting }o--o{ User : "users"
    MeetingAgendaItem ||--o{ MeetingActionItem : "actionItems"
    MeetingAgendaItem ||--o{ MeetingDecision : "decisions"
    MeetingDateProposal ||--o{ MeetingDateVote : "votes"
    MeetingUser ||--o| Payment : "payment"
    Training }o--o{ User : "trainees"
    TrainingLevel ||--o{ TrainingPack : "packs"
    TrainingLevel ||--o{ Training : "sessions"
    TrainingPack ||--o| EventPost : "eventPost"
    TrainingPack }o--o{ Subscription : "subscriptions"
    TrainingPack ||--o{ Training : "trainings"
    TrainingPlan ||--o{ TrainingPlanAssignment : "assignments"
    TrainingPlan ||--o{ TrainingPlanPack : "packs"
    TrainingPlanPack ||--o{ TrainingPlanAssignment : "assignments"
```
