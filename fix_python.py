with open('admin_your_rank_super.php', 'rb') as f:
    content = f.read()

# PowerShell Set-Content UTF8 writes a BOM, let's strip it
if content.startswith(b'\xef\xbb\xbf'):
    content = content[3:]

# Decode it as utf-8 (which gives us the literal mojibake string)
mojibake = content.decode('utf-8')

# Now encode it back to Windows-1252 to get the original bytes
try:
    original_bytes = mojibake.encode('cp1252')
except Exception as e:
    original_bytes = mojibake.encode('cp1252', errors='replace')

# Save it
with open('admin_your_rank_super_restored.php', 'wb') as f:
    f.write(original_bytes)
