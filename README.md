# Date Range field for Craft CMS

What it says on the tin 🙂. This field gives you a start and end date in one field, and some easy-to-use query filters.

<img src="https://www.studioespresso.co/assets/date-range-github-banner.png">

## Requirements

This plugin requires Craft CMS 3, 4 or 5.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project
        composer require studioespresso/craft-date-range
        ./craft install/plugin date-range

## Settings
The following options can be set on the field:
- Show a start time field
- Show an end time field
- End date should be after or later thand the start date

When the field is set to required, both start & end date (and if enabled time) will be required. 

## Default time values
Since a PHP ``DateTime`` object also has a time value, wether you entered on or not (or wether you have to option enabled to show the fields or not), the plugin tries to be smart in which time values get saved.

When you enable either or both time fields, that value will, of course, be saved. For fields that don't have time options set, ``00:00:00`` or ``23:59`` will get saved.

## Upgrading to Craft 5
With Craft 5 comes multi-instance support (fields can be used multiple times in the same layout), and this adds a bit of complexity for the Date Range plugin.
In the query behaviour, you'll need to add the handle of the entry type you're trying to query as a second argument.

```twig
// Craft 4
{% set events = craft.entries.section('events').isFuture('dateRangeFieldHandle')  %}

// Craft 5
{% set events = craft.entries.section('events').isFuture('dateRangeFieldHandle', 'entryTypeHandle')  %}
````

## Templating

### Element queries
⚠️ Using date range fields in your entry queries is possible but requires the server to be running **MySQL 5.7 or later** or **PostgreSQL 9.3 or later**.

Example:

```twig
{% set events = craft.entries.section('events').isFuture('dateRangeFieldHandle', 'entryTypeHandle')  %}
```

The plugin includes `isOnGoing()`, `isPast()`,`isNotPast()`  and `isFuture()` query behaviours.
The second argument passed should be the handle of the entry type you want to query.

You can optionally pass `true` as a third argument to the query to make it include events that happen today in future/past/onGoing queries. 

### Field values
When using the field in your template, you have access to both `start` and `end` properties, as well as:
- `getFormatted()`: which optionally accepts a date(time) format (eg: 'd/m/Y') as the first parameter and a seperator string as the second (eg: ' until ').
- `isPast`: returns `true` if the `end` property is past the current date & time.
- `isFuture`: returns `true` if the `start` property is ahead the current date & time.
- `isOnGoing`: returns `true` if the `start` property is past the current date & time *and* the `end` property is ahead of the current date & time.

### `getFormatted()`
When using the ``getFormatted()`` function, you can pass paramters in two ways:
1) a date format and a separator string (e.g.: ``entry.dateRangeHandle.formatted("d/m/Y Hi", "until"|t)``)  
2) an array with a ``date`` and a ``time`` key and a separator string (eg: ``entry.dateRangeHandle.formatted({ date: 'd/m/Y', time: 'H:i:s'}, 'tot'|t)``)

With this second option, the field can output date and time seperatly and when the start and end dates are the same, it will only ouput one, using the separate time formates for the start and end times (eg `` 30/04/2020 11:00 until 16:00`` )

## GraphQL
The field has full support for Craft's GraphQL api, which was added in Craft CMS 3.3
You have access to the same properties as you do in Twig, and you can also use Craft's ``@formatDateTime`` to change the date formats.  

### Format of GQL arguments:
- isFuture, isPast, isNotPast, isOngoing: [`"propertyName"`, `"entryTypeHandle,entryTypeHandle2..."`, `<includesToday>`]
- isAfter, isBefore: [`"propertyName"`, `"entryTypeHandle,entryTypeHandle2..."`, `<DateTime>`],
- isBetween: [`"propertyName"`, `"entryTypeHandle,entryTypeHandle2..."`, `<DateTime>`, `<DateTime>`],

### isFuture
```graphql
query {
  entries(
   section: "events",
   isFuture: ["dateRangeFieldHandle", "events", true]
  ) {
    title
    ... on events_events_Entry {
      dateRangeFieldHandle {
        start
        end @formatDateTime(format: "d M Y")
        isPast
        isOnGoing
        isFuture
      }
    }
  }
}
```

### isAfter, isBefore
~~~graphql
query {
  entries(
   section: "events",
   isAfter: ["dateRangeFieldHandle", "events", "2020-01-01 00:00:00"]
  ) {
    title
    ... on events_events_Entry {
      dateRangeFieldHandle {
        start
        end @formatDateTime(format: "d M Y")
        isPast
        isOnGoing
        isFuture
      }
    }
  }
}
~~~

### isBetween
~~~graphql
query {
  entries(
   section: "events",
   isBetween: ["dateRangeFieldHandle", "events", "2020-01-01 00:00:00", "2030-01-31 23:59"]
  ) {
    title
    ... on events_events_Entry {
      dateRangeFieldHandle {
        start
        end @formatDateTime(format: "d M Y")
        isPast
        isOnGoing
        isFuture
      }
    }
  }
}
~~~
----

Brought to you by [Studio Espresso](https://www.studioespresso.co)
