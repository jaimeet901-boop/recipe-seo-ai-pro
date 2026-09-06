from pathlib import Path
import re

path = Path('includes/class-rsaip-recipe-optimizer.php')
text = path.read_text(encoding='utf-8')
pattern = r"\$html \.= '<script type="text/javascript">.*?</script>';"
new_text, count = re.subn(pattern, '', text, flags=re.S)
if count == 0:
    raise SystemExit('No inline script injection found')
path.write_text(new_text, encoding='utf-8')
print(f'Removed {count} inline script injection(s) from {path}')
