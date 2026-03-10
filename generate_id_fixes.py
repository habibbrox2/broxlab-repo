import os
import re

db_dir = r'E:\xampp-server\broxbhai\Database'
output_file = r'E:\xampp-server\broxbhai\Database\auto_fix_ids.sql'

fix_statements = []

for filename in os.listdir(db_dir):
    if filename.endswith('_structure.sql'):
        with open(os.path.join(db_dir, filename), 'r', encoding='utf-8') as f:
            content = f.read()
            
            # Extract table name
            table_match = re.search(r'CREATE TABLE IF NOT EXISTS `([^`]+)`', content)
            if not table_match:
                continue
            table_name = table_match.group(1)
            
            # Check for id column and its type
            id_match = re.search(r'`id` (int|bigint)\(\d+\)( unsigned)? NOT NULL', content, re.IGNORECASE)
            if id_match:
                col_type = id_match.group(1).lower()
                is_unsigned = id_match.group(2) or ""
                
                # Check if it already has primary key or auto_increment in the same line
                if 'AUTO_INCREMENT' not in id_match.group(0):
                    # Check if PRIMARY KEY is defined later in the file
                    has_pk = 'PRIMARY KEY (`id`)' in content or 'PRIMARY KEY (`id`)' in content.replace(' ', '')
                    
                    if has_pk:
                        fix_statements.append(f"ALTER TABLE `{table_name}` MODIFY `id` {col_type}(11){is_unsigned} NOT NULL AUTO_INCREMENT;")
                    else:
                        fix_statements.append(f"ALTER TABLE `{table_name}` MODIFY `id` {col_type}(11){is_unsigned} NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`);")

with open(output_file, 'w', encoding='utf-8') as f:
    f.write("-- Auto-generated fix for missing AUTO_INCREMENT and PRIMARY KEY\n")
    f.write("SET FOREIGN_KEY_CHECKS = 0;\n\n")
    f.write("\n".join(fix_statements))
    f.write("\n\nSET FOREIGN_KEY_CHECKS = 1;\n")

print(f"Generated {len(fix_statements)} fix statements in {output_file}")
