# Testing Information

## Type of test

We use PEST tests : https://pestphp.com/

## Command lines

1. To launch all the testing:

    ```bash
      ./vendor/bin/pest 
    ```
2. To test and display a list of project files and their corresponding coverage results.

    ```bash
      ./vendor/bin/pest --coverage 
    ```
   NB: If you want to display the coverage report: go to tests >Coverage >html >index.html
4. Type coverage : to check if we always gave a type to each variable, parameter and return

    ```bash
          php -d memory_limit=1G vendor/bin/pest --type-coverage
    ```

4. To use all the Pest plugin for Laravel (actingAs etc)
    
    ```bash
      composer require pestphp/pest-plugin-laravel --dev
   ```

## Architecture of tests

The tests are classified following this pyramid principle:

```
         ▲  Tests Controller (Feature)
        ▲▲▲  → HTTP, auth, validation
       ▲▲▲▲▲  Tests Service (Unit)
      ▲▲▲▲▲▲▲  → logique métier, emails
```
with these rules:
- In Unit if it's pure PHP testing (it needs to work without internet)
- In Feature if it depends on the framework (ex: mail, uses queues, etc)
- We follow the single responsability rule:
  - ControllerTest: test the redirect, auth, HTTP response.
  - RequestTest: testing validation
  - ServiceTest: testing needing Laravel tools (ex: mail).
  - ActionTest: test interactions with DB
  - Unit (Enums, casts, etc): pure PHP logic testing
and this structure:
1. Architecture:
    - Contains the tests concerning the app's architecture and good practices like strict types in every file etc
    - Has to be run and passed before every pull request into dev branch
2. Feature:
   - Contains the tests organized by feature (contact, etc)
   - Cheks if everything runs smoothly
3. Unit:
   - Contains the tests for all the logical aspects (services, actions, etc)
   - Organized following the app architecture
4. Coverage:
    - Contains the coverage rapport for the pest testing

We use the group function in testing, allowing us to make the testing by feature (including unit etc):

```pest
    // For example
    test('Something')->group('contact');
```

```bash
    php artisan test --group=contact
```
