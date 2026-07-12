---
name: Bug report
about: Report something that isn't working as expected
title: ''
labels: bug
assignees: ''
---

**Describe the bug**
A clear description of what went wrong.

**To reproduce**
The query and the `queue(...)` call that triggers it:

```php
User::where(/* ... */)->queue(/* ... */)->delete()->dispatch();
```

**Expected behavior**
What you expected to happen.

**Environment**
- queue-sql version:
- Laravel version:
- PHP version:
- Queue driver (sync / database / redis / sqs):
- Database (mysql / pgsql / sqlite):

**Additional context**
Stack traces, `failed_jobs` rows, or anything else that helps.
