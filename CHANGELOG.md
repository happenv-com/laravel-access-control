# Changelog

All notable changes to `access-control` will be documented in this file.

## 2.1.0 - 2026-09-03

### Added

- `PermissionSubjectDto` — the level between a group and its permissions, one subject per permission enum. A group answers which module owns a permission; a subject answers what the permission is about.
- `PermissionGroupDto::$subjects` — the group's permissions grouped by the enum that declares them, keyed by that enum's class name. `$children` is unchanged and still holds the flat list.
- `PermissionCollection::getSubjects()` — every subject across every group.
- `PermissionReflector::getSubject()`, `getSubjectName()`, `getSubjectDescription()` and `getSubjectSlug()`.
- `PermissionName` and `PermissionDescription` are now read from the enum CLASS as well as from its cases, where they label the subject. Without one, the subject is named after the enum with the `Permission` suffix dropped (`WarehousePermission` → `Warehouse`).

### Changed

- The default name of a permission without its own `PermissionName` is now qualified with its SUBJECT instead of its group (`Delete Product`, not `Delete Products`). A group shared by several enums rendered every one of their `Delete` cases as the same unusable string.

## 1.0.0 - 202X-XX-XX

- initial release
