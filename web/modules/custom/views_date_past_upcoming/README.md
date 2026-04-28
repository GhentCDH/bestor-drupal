# views_date_past_upcoming

Views field and sort plugins for classifying dates as past or upcoming.

## Purpose

Provides two Views plugins that classify a date field as past or upcoming relative to the current moment: a computed field that returns the classification label, and a sort that orders results by that classification (upcoming first).

## Architecture overview

```
src/Plugin/views/
├── field/DatePastUpcoming.php   — computed Views field: past / upcoming label
└── sort/DatePastUpcomingSort.php — custom sort by past/upcoming classification
```

## Use case

Events listing with "upcoming first" ordering, where dates before today should be grouped separately from future dates.

## Dependencies

- `drupal:views`
- No custom module dependencies
