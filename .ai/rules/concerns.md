---
paths:
  - app/Concerns/NormalizesUniqueAttributes.php
---

# Concerns

## NormalizesUniqueAttributes clears the shadow column on null
If a normalized-unique attribute is nullable (e.g. Article's barcode), setting it to null also clears its `_normalized` shadow column to null — it doesn't just skip normalization. Without this, clearing a previously-set value would leave the old normalized value in place and permanently block reuse by another record, since a stale non-null normalized value stays "taken" even though the visible column is now null. Non-nullable normalized attributes (Category.name, Brand.name, etc.) are unaffected.
