from pathlib import Path
import re
import subprocess
import sys


class JsMinificationResult:
    file: str
    output: str

    def __init__(self, file: Path):
        self.file = file
        process = subprocess.run(['node_modules/.bin/uglifyjs', file, '-m', 'toplevel', '-c', '--toplevel'],
            stdout=subprocess.PIPE)
        if process.returncode != 0:
            print('uglifyjs failed for', file)
            sys.exit(1)
        self.output = process.stdout.decode().strip()


files = []
for file in Path('modules/inline').glob('*.js'):
    files.append(JsMinificationResult(file))


with open('includes/InlineJsConstants.php', 'wt') as fp:
    fp.write('\n'.join([
        '<?php',
        'namespace MediaWiki\\Extension\\Ark\\ThemeToggle;',
        '',
        'class InlineJsConstants {\n'
    ]))
    for result in files:
        constant_name = re.sub(r"([A-Z])", '_\\1', result.file.name)
        constant_name = constant_name.split('.', 1)[0]
        constant_name = constant_name.replace('.', '_').upper()
        fp.write('\n'.join([
            f'    // {result.file}',
            f'    const {constant_name} = \'{result.output}\';',
            ''
        ]))
    fp.write('}')
