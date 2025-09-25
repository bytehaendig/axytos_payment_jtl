#!/bin/bash
set -e

echo "Extracting translatable strings to messages.pot..."

# Extract strings from PHP files
find . -name "*.php" -not -path "./vendor/*" -not -path "./tests/*" | xargs xgettext \
  --language=PHP \
  --keyword=__ \
  --keyword=_ \
  --from-code=UTF-8 \
  --output=locale/messages.pot \
  --package-name=axytos_payment \
  --msgid-bugs-address=dev@axytos.com

# Extract strings from template files (preprocess to temp files with relative paths)
find . -name "*.tpl" -not -path "./vendor/*" -not -path "./tests/*" | while read -r file; do
    # Create a temp file with the relative path structure
    temp_file="${file}.tmp.php"
    mkdir -p "$(dirname "$temp_file")"
    # Convert Smarty {__("string")} to PHP <?php __("string"); ?>
    sed 's/{__(\(.*\))}/<?php __(\1); ?>/g' "$file" > "$temp_file"

    # Extract from temp file
    xgettext \
      --language=PHP \
      --keyword=__ \
      --keyword=_ \
      --from-code=UTF-8 \
      --output=locale/messages.pot \
      --join-existing \
      --package-name=axytos_payment \
      --msgid-bugs-address=dev@axytos.com \
      "$temp_file"

    # Clean up
    rm -f "$temp_file"
done

echo "✓ Created locale/messages.pot"
echo "Next steps:"
echo "  - Run 'mise run i18n-po-de' to update German translations"
echo "  - Run 'mise run i18n-po-en' to update English translations"
echo "  - Translate new strings in the .po files"
echo "  - Run 'mise run i18n-mo-de' and 'mise run i18n-mo-en' to compile"