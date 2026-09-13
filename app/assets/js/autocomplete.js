/**
* ═══════════════════════════════════════════════════════════════════════════
*  QUIRKY IDE — AUTOCOMPLETE ENGINE v11 (MAJOR REWRITE)
* ═══════════════════════════════════════════════════════════════════════════
*
*  Self-owned popup + single insertion engine (CM5 and CM6 via the
*  editor-adapter facade).
*
*  ★ v11 changes over v16:
*   • COMPLETE FUNCTION INSERTIONS — picks like password_hash now insert
*     password_hash($password); — named params parsed from the pool detail,
*     a SMART statement ";" (only at line-end, never inside conditions or
*     argument lists), the first placeholder auto-SELECTED so typing
*     replaces it, and Tab-stops through every remaining placeholder.
*   • EMPTY-FILE RANKING — a blank file ranks BOILERPLATES first and
*     snippets second; a file with code ranks snippets first. Sticky
*     dividers separate the two groups.
*   • LIVE RENAME SYNC — file:renamed purges the caches and re-points the
*     language at the new extension instantly (no close/reopen).
*   • Completed/audited pools: PHP function details got full parameter
*     names; added cout/sout aliases; stale bare-paren insertions are gone.
*
*  EXPOSES: window.IDE.autocomplete =
*    { trigger(), triggerBoilerplates(), close(), isEnabled(), setEnabled(),
*      isAutoPopup(), setAutoPopup(), registerPool(), installPool(),
*      popupOpen(), pick(), move(), jumpStop() }
* ═══════════════════════════════════════════════════════════════════════════
*/
(function () {
  'use strict';

  window.IDE = window.IDE || {};
  var U = window.IDE.utils || null;

  var MIDOT = '·';                 // one-indent-unit marker in snippet bodies
  var CURSOR_MARKER = '\uE000';    // invisible "put the caret here" token
  var TAB_STOP = '\uE001';         // invisible "Tab jumps here next" token
  var SEMI_TOKEN = '\uE002';       // ★ v11: resolves to ";" only at line-end
  var AUTO_DEBOUNCE_MS = 120;
  var COMPOSING_DEBOUNCE_MS = 300;
  var MAX_RESULTS = 60;
  var MANUAL_RESULTS = 40;
  var DOC_WORD_LIMIT = 600;
  var DOC_WORD_MAX_BYTES = 200000;
  var PERF_LINES = 5000;
  var PERF_CHARS = 1000000;
  var LS_AUTO_POPUP = 'quirky.ide.settings.acAutoPopup';
  var LS_RECENT = 'quirky.ide.ac.recent';
  var LS_POOLS = 'quirky.ide.ac.pools';

  /* ★ v11: languages where a completed call is a statement ending in ";" */
  var SEMI_LANGS = { php: 1, javascript: 1, c: 1, cpp: 1, java: 1, csharp: 1, dart: 1, swift: 1, sql: 1, perl: 1 };

  var POOLS = {
    /* ─────────────────────────────── PHP ─────────────────────────────── */
    php: {
      keywords: ['abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch',
        'class', 'clone', 'const', 'continue', 'declare', 'default', 'do', 'echo', 'else',
        'elseif', 'empty', 'endforeach', 'endif', 'extends', 'final', 'finally', 'fn',
        'for', 'foreach', 'function', 'global', 'if', 'implements', 'include',
        'include_once', 'instanceof', 'insteadof', 'interface', 'isset', 'list', 'match',
        'namespace', 'new', 'null', 'or', 'print', 'private', 'protected', 'public',
        'readonly', 'require', 'require_once', 'return', 'static', 'switch', 'throw',
        'trait', 'try', 'unset', 'use', 'var', 'while', 'xor', 'yield', 'true', 'false'],
      variables: ['$_GET', '$_POST', '$_REQUEST', '$_SESSION', '$_COOKIE', '$_SERVER',
        '$_FILES', '$_ENV', '$GLOBALS', '$this'],
      functions: [
        /* ★ v11: full parameter names — picks insert complete calls */
        'strlen|(string)', 'str_replace|(search,replace,subject)', 'substr|(string,start,length?)',
        'strpos|(haystack,needle)', 'strtoupper|(string)', 'strtolower|(string)', 'ucfirst|(string)',
        'trim|(string,chars?)', 'explode|(separator,string)→array', 'implode|(glue,pieces)',
        'sprintf|(format,...)', 'number_format|(number,decimals?)', 'preg_match|(pattern,subject,matches?)',
        'preg_replace|(pattern,replacement,subject)', 'array_push|(array,values)', 'array_pop|(array)',
        'array_shift|(array)', 'array_map|(callback,array)', 'array_filter|(array,callback?)',
        'array_reduce|(array,callback,initial)', 'array_merge|(...arrays)', 'array_keys|(array)',
        'array_values|(array)', 'array_slice|(array,offset,length?)', 'array_search|(needle,array)',
        'in_array|(needle,haystack)', 'count|(array)', 'sort|(array)', 'usort|(array,callback)',
        'json_encode|(value,flags?)', 'json_decode|(json,associative?)',
        'file_get_contents|(filename)', 'file_put_contents|(filename,data)', 'fopen|(filename,mode)',
        'fwrite|(handle,string)', 'fclose|(handle)', 'file_exists|(filename)→bool', 'is_dir|(filename)',
        'mkdir|(directory,recursive?)', 'unlink|(filename)', 'scandir|(directory)', 'date|(format,timestamp?)',
        'time|()', 'strtotime|(string)', 'rand|(min,max)', 'abs|(number)', 'max|(...values)',
        'min|(...values)', 'round|(value,precision?)', 'floor|(value)', 'ceil|(value)', 'intval|(value)',
        'var_dump|(...values)', 'print_r|(value)', 'header|(header)',
        'session_start|()', 'setcookie|(name,value)', 'md5|(string)', 'hash|(algorithm,string)',
        'password_hash|(password)', 'password_verify|(password,hash)',
        'htmlspecialchars|(string)', 'strip_tags|(string)', 'urlencode|(string)'
      ],
      snippets: [
        { label: 'phptag', detail: '<?php … ?> block', body: '<?php\n··\n?>' },
        { label: 'if', body: 'if ($cond) {\n··\n}' },
        { label: 'ifelse', body: 'if ($cond) {\n··\n} else {\n··\n}' },
        { label: 'foreach', body: 'foreach ($items as $item) {\n··\n}' },
        { label: 'foreach-kv', detail: 'key => value form', body: 'foreach ($arr as $key => $value) {\n··\n}' },
        { label: 'for', body: 'for ($i = 0; $i < count($arr); $i++) {\n··\n}' },
        { label: 'while', body: 'while ($cond) {\n··\n}' },
        { label: 'function', body: 'function name($arg) {\n··\n}' },
        { label: 'method', detail: 'public function', body: 'public function name($arg) {\n··\n}' },
        { label: 'class', body: 'class Name {\n··public function __construct() {\n····\n··}\n}' },
        { label: 'interface', body: 'interface Name {\n··\n}' },
        { label: 'trait', body: 'trait Name {\n··\n}' },
        { label: 'trycatch', body: 'try {\n··\n} catch (Exception $e) {\n··echo $e->getMessage();\n}' },
        { label: 'array', detail: '[ key => value ]', body: "$arr = [\n··'key' => 'value',\n];" },
        { label: 'switch', body: "switch ($var) {\n··case 'value':\n····break;\n··default:\n····break;\n}" },
        { label: 'json-response', detail: 'JSON header + exit', body: "header('Content-Type: application/json');\necho json_encode($data);\nexit;" },
        { label: 'ternary', body: '$result = $cond ? $a : $b;' },
        { label: 'nullcheck', detail: '?? operator', body: '$val = $maybe ?? $fallback;' }
      ]
    },
    /* ─────────────────────────────── HTML ────────────────────────────── */
    html: {
      keywords: ['class', 'id', 'href', 'src', 'alt', 'style', 'width', 'height', 'type',
        'name', 'value', 'placeholder', 'action', 'method', 'target', 'rel', 'disabled',
        'checked', 'required', 'readonly', 'selected', 'multiple', 'autocomplete',
        'title', 'data-', 'aria-label', 'role', 'charset', 'content', 'loading'],
      functions: [],
      snippets: [
        { label: 'html5', detail: 'full page skeleton', body: '<!DOCTYPE html>\n<html lang="en">\n<head>\n··<meta charset="UTF-8">\n··<meta name="viewport" content="width=device-width, initial-scale=1.0">\n··<title>Document</title>\n</head>\n<body>\n··\n</body>\n</html>' },
        { label: 'div', body: '<div class="">\n··\n</div>' },
        { label: 'span', body: '<span>' + CURSOR_MARKER + '</span>' },
        { label: 'p', body: '<p>' + CURSOR_MARKER + '</p>' },
        { label: 'a', body: '<a href="#">' + CURSOR_MARKER + '</a>' },
        { label: 'img', body: '<img src="" alt="">' },
        { label: 'ul', body: '<ul>\n··<li></li>\n··<li></li>\n</ul>' },
        { label: 'ol', body: '<ol>\n··<li></li>\n··<li></li>\n</ol>' },
        { label: 'li', body: '<li>' + CURSOR_MARKER + '</li>' },
        { label: 'table', body: '<table>\n··<thead>\n····<tr><th>Col</th></tr>\n··</thead>\n··<tbody>\n····<tr><td>Data</td></tr>\n··</tbody>\n</table>' },
        { label: 'tr', body: '<tr>\n··<td></td>\n</tr>' },
        { label: 'form', body: '<form action="" method="post">\n··\n</form>' },
        { label: 'input', body: '<input type="text" name="" placeholder="">' },
        { label: 'button', body: '<button type="button">' + CURSOR_MARKER + '</button>' },
        { label: 'textarea', body: '<textarea name="" rows="4"></textarea>' },
        { label: 'select', body: '<select name="">\n··<option value=""></option>\n</select>' },
        { label: 'label', body: '<label for=""></label>' },
        { label: 'script', body: '<script>\n··\n</script>' },
        { label: 'script-src', body: '<script src=""></script>' },
        { label: 'style', body: '<style>\n··\n</style>' },
        { label: 'link-css', body: '<link rel="stylesheet" href="">' },
        { label: 'meta-viewport', body: '<meta name="viewport" content="width=device-width, initial-scale=1.0">' },
        { label: 'title', body: '<title></title>' },
        { label: 'h1', body: '<h1>' + CURSOR_MARKER + '</h1>' },
        { label: 'h2', body: '<h2>' + CURSOR_MARKER + '</h2>' },
        { label: 'h3', body: '<h3>' + CURSOR_MARKER + '</h3>' },
        { label: 'header', body: '<header>\n··\n</header>' },
        { label: 'footer', body: '<footer>\n··\n</footer>' },
        { label: 'nav', body: '<nav>\n··\n</nav>' },
        { label: 'section', body: '<section>\n··\n</section>' },
        { label: 'article', body: '<article>\n··\n</article>' },
        { label: 'iframe', body: '<iframe src="" width="100%" height="300"></iframe>' },
        { label: 'video', body: '<video src="" controls></video>' }
      ]
    },
    /* ─────────────────────────────── CSS ─────────────────────────────── */
    css: {
      keywords: ['color', 'background', 'background-color', 'background-image',
        'font-size', 'font-family', 'font-weight', 'font-style', 'margin', 'padding',
        'border', 'border-radius', 'width', 'height', 'max-width', 'min-height',
        'display', 'position', 'top', 'left', 'right', 'bottom', 'z-index', 'overflow',
        'opacity', 'cursor', 'box-shadow', 'text-align', 'text-transform',
        'letter-spacing', 'line-height', 'flex', 'flex-direction', 'justify-content',
        'align-items', 'gap', 'grid-template-columns', 'transition', 'transform',
        'animation', 'content', 'visibility', 'white-space', 'box-sizing',
        'pointer-events', 'outline', 'text-decoration', 'vertical-align'],
      functions: [
        'display|block·inline·flex·grid·none', 'position|static·relative·absolute·fixed·sticky',
        'justify-content|flex-start·center·space-between·flex-end',
        'align-items|stretch·center·flex-start·flex-end',
        'flex-direction|row·column·row-reverse', 'text-align|left·center·right·justify',
        'font-weight|normal·bold·400·700', 'overflow|visible·hidden·scroll·auto',
        'box-sizing|border-box·content-box', 'cursor|pointer·default·not-allowed·move',
        'border-style|solid·dashed·dotted·none', 'pointer-events|auto·none',
        'white-space|normal·nowrap·pre-wrap', 'calc|(100% - 20px)',
        'var|(--prop,fallback)', 'linear-gradient|(angle,color-stops)',
        'rgba|(red,green,blue,alpha)'
      ],
      snippets: [
        { label: 'rule', detail: 'selector block', body: 'selector {\n··property: value;\n}' },
        { label: 'flex-center', body: 'display: flex;\nalign-items: center;\njustify-content: center;' },
        { label: 'flex-column', body: 'display: flex;\nflex-direction: column;\ngap: 8px;' },
        { label: 'media-query', body: '@media (max-width: 768px) {\n··\n}' },
        { label: 'keyframes', body: '@keyframes name {\n··from { opacity: 0; }\n··to { opacity: 1; }\n}' },
        { label: 'transition', body: 'transition: all 0.2s ease;' },
        { label: 'absolute-fill', detail: 'pin to parent edges', body: 'position: absolute;\ntop: 0;\nleft: 0;\nright: 0;\nbottom: 0;' },
        { label: 'center-abs', detail: 'absolute centering', body: 'position: absolute;\ntop: 50%;\nleft: 50%;\ntransform: translate(-50%, -50%);' },
        { label: 'ellipsis', detail: 'truncate one line', body: 'overflow: hidden;\ntext-overflow: ellipsis;\nwhite-space: nowrap;' },
        { label: 'card-shadow', body: 'box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);\nborder-radius: 8px;' },
        { label: 'grid-auto', body: 'display: grid;\ngrid-template-columns: repeat(auto-fill, minmax(180px, 1fr));\ngap: 12px;' },
        { label: 'reset-box', body: '*, *::before, *::after {\n··box-sizing: border-box;\n··margin: 0;\n··padding: 0;\n}' }
      ]
    },
    /* ── JavaScript (+ TypeScript bonus keywords/snippets) ────────────── */
    javascript: {
      keywords: ['const', 'let', 'var', 'function', 'return', 'if', 'else', 'for', 'while',
        'do', 'switch', 'case', 'break', 'continue', 'new', 'delete', 'typeof',
        'instanceof', 'in', 'of', 'class', 'extends', 'super', 'this', 'try', 'catch',
        'finally', 'throw', 'async', 'await', 'yield', 'import', 'export', 'default',
        'from', 'as', 'static', 'get', 'set', 'void', 'debugger', 'null', 'undefined',
        'true', 'false',
        'interface', 'type', 'enum', 'implements', 'private', 'public', 'protected',
        'readonly', 'namespace', 'declare', 'abstract', 'keyof', 'unknown', 'never'],
      functions: [
        'console.log|(...args)', 'console.warn|(...args)', 'console.error|(...args)',
        'setTimeout|(callback,ms)', 'setInterval|(callback,ms)', 'clearTimeout|(id)',
        'clearInterval|(id)', 'requestAnimationFrame|(callback)', 'fetch|(url,options?)→Promise',
        'Promise.all|(promises)', 'JSON.parse|(text)', 'JSON.stringify|(value,space?)',
        'Object.keys|(obj)', 'Object.values|(obj)', 'Object.entries|(obj)',
        'Object.assign|(target,source)', 'Array.isArray|(value)', 'Array.from|(iterable)',
        'Math.abs|(x)', 'Math.max|(...values)', 'Math.min|(...values)', 'Math.floor|(x)',
        'Math.ceil|(x)', 'Math.round|(x)', 'Math.random|()',
        'Number.parseInt|(string)', 'document.querySelector|(selector)',
        'document.querySelectorAll|(selector)', 'document.getElementById|(id)',
        'document.createElement|(tag)', 'addEventListener|(type,listener)',
        'localStorage.getItem|(key)', 'localStorage.setItem|(key,value)',
        'map|arr.map(callback)', 'filter|arr.filter(callback)', 'reduce|arr.reduce(callback,initial)',
        'forEach|arr.forEach(callback)', 'find|arr.find(predicate)', 'includes|.includes(value)',
        'indexOf|.indexOf(value)', 'slice|.slice(start,end?)', 'splice|.splice(start,deleteCount)',
        'join|arr.join(separator)', 'split|str.split(separator)', 'push|arr.push(value)',
        'trim|str.trim()', 'replace|str.replace(search,replacement)', 'replaceAll|str.replaceAll(search,replacement)',
        'toLowerCase|()', 'toUpperCase|()', 'startsWith|str.startsWith(prefix)'
      ],
      snippets: [
        { label: 'fn', detail: 'function declaration', body: 'function name(arg) {\n··\n}' },
        { label: 'arrow', detail: 'const arrow fn', body: 'const name = (arg) => {\n··\n};' },
        { label: 'asyncfn', body: 'async function name(arg) {\n··\n}' },
        { label: 'for', body: 'for (let i = 0; i < arr.length; i++) {\n··\n}' },
        { label: 'forof', body: 'for (const item of items) {\n··\n}' },
        { label: 'ifelse', body: 'if (cond) {\n··\n} else {\n··\n}' },
        { label: 'trycatch', body: 'try {\n··\n} catch (err) {\n··console.error(err);\n}' },
        { label: 'promise', body: 'new Promise((resolve, reject) => {\n··\n});' },
        { label: 'then-chain', body: 'fetch(url)\n··.then((res) => res.json())\n··.then((data) => {\n····\n··})\n··.catch((err) => console.error(err));' },
        { label: 'await-fetch', detail: 'async GET + json', body: 'const res = await fetch(url);\nconst data = await res.json();' },
        { label: 'post-json', body: "await fetch(url, {\n··method: 'POST',\n··headers: { 'Content-Type': 'application/json' },\n··body: JSON.stringify(payload)\n});" },
        { label: 'class', body: 'class Name {\n··constructor(arg) {\n····\n··}\n}' },
        { label: 'delayed', body: 'setTimeout(() => {\n··\n}, 500);' },
        { label: 'domready', body: "document.addEventListener('DOMContentLoaded', () => {\n··\n});" },
        { label: 'listener', body: "el.addEventListener('click', (e) => {\n··\n});" },
        { label: 'map-lambda', body: 'const result = items.map((item) => item.value);' },
        { label: 'reduce-sum', body: 'const total = nums.reduce((sum, n) => sum + n, 0);' },
        { label: 'ts-interface', detail: 'TypeScript bonus', body: 'interface Name {\n··prop: string;\n}' },
        { label: 'ts-type', detail: 'TypeScript bonus', body: 'type Name = {\n··prop: string;\n};' }
      ]
    },
    /* ────────────────────────────── PYTHON ───────────────────────────── */
    python: {
      keywords: ['def', 'class', 'return', 'if', 'elif', 'else', 'for', 'while', 'break',
        'continue', 'pass', 'import', 'from', 'as', 'with', 'try', 'except', 'finally',
        'raise', 'lambda', 'global', 'nonlocal', 'assert', 'del', 'yield', 'async',
        'await', 'not', 'and', 'or', 'in', 'is', 'None', 'True', 'False', 'match', 'case'],
      variables: ['self', 'cls', '__name__', '__main__', '__init__'],
      functions: [
        'print|(*args)', 'len|(obj)', 'range|(start,stop,step?)', 'enumerate|(iterable,start=0)',
        'zip|(*iterables)', 'map|(function,iterable)', 'filter|(function,iterable)', 'sorted|(iterable,key?,reverse?)',
        'reversed|(sequence)', 'sum|(iterable)', 'min|(...values)', 'max|(...values)', 'abs|(x)', 'round|(x,digits?)',
        'type|(obj)', 'isinstance|(obj,class)', 'str|(obj)', 'int|(x)', 'float|(x)',
        'bool|(x)', 'list|(iterable?)', 'tuple|(iterable?)', 'dict|(**kwargs)', 'set|(iterable?)',
        'open|(path,mode)', 'input|(prompt?)', 'any|(iterable)', 'all|(iterable)',
        'hasattr|(obj,name)', 'getattr|(obj,name)', 'super|().__init__()',
        'os.listdir|(path)', 'os.getcwd|()', 'os.path.join|(*parts)',
        'os.path.exists|(path)', 'sys.exit|(code?)', 'sys.argv|CLI args',
        'json.dumps|(obj,indent?)', 'json.loads|(string)', 're.match|(pattern,string)',
        're.search|(pattern,string)', 're.sub|(pattern,replacement,string)', 're.findall|(pattern,string)',
        'math.sqrt|(x)', 'random.randint|(a,b)', 'random.choice|(sequence)',
        'datetime.now|()', 'Counter|collections.Counter(iterable)'
      ],
      snippets: [
        { label: 'def', body: 'def func_name(arg):\n··pass' },
        { label: 'class', body: 'class ClassName:\n··def __init__(self, arg):\n····self.arg = arg' },
        { label: 'main-guard', detail: 'if __name__ == "__main__"', body: "if __name__ == '__main__':\n··main()" },
        { label: 'for-range', body: 'for i in range(n):\n··pass' },
        { label: 'for-enum', body: 'for i, item in enumerate(items):\n··pass' },
        { label: 'while', body: 'while cond:\n··pass' },
        { label: 'ifelse', body: 'if cond:\n··pass\nelif other:\n··pass\nelse:\n··pass' },
        { label: 'tryexcept', body: 'try:\n··pass\nexcept Exception as e:\n··print(e)' },
        { label: 'with-open', detail: 'read file', body: "with open(path, 'r', encoding='utf-8') as f:\n··content = f.read()" },
        { label: 'with-write', detail: 'write file', body: "with open(path, 'w', encoding='utf-8') as f:\n··f.write(data)" },
        { label: 'listcomp', body: 'result = [x for x in items if x]' },
        { label: 'dictcomp', body: 'result = {k: v for k, v in pairs}' },
        { label: 'raise', body: "raise ValueError('message')" },
        { label: 'dataclass', detail: 'needs dataclasses import', body: '@dataclass\nclass Point:\n··x: int\n··y: int' }
      ]
    },
    /* ─────────────────────────────── SQL ─────────────────────────────── */
    sql: {
      keywords: ['SELECT', 'FROM', 'WHERE', 'INSERT', 'INTO', 'VALUES', 'UPDATE', 'SET',
        'DELETE', 'JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'INNER JOIN', 'ON', 'GROUP BY',
        'ORDER BY', 'HAVING', 'LIMIT', 'OFFSET', 'UNION', 'UNION ALL', 'DISTINCT', 'AS',
        'AND', 'OR', 'NOT', 'NULL', 'IS NULL', 'IN', 'LIKE', 'BETWEEN', 'EXISTS', 'CASE',
        'WHEN', 'THEN', 'ELSE', 'END', 'CREATE TABLE', 'ALTER TABLE', 'DROP TABLE',
        'INDEX', 'VIEW', 'PRIMARY KEY', 'FOREIGN KEY', 'REFERENCES', 'AUTO_INCREMENT',
        'DEFAULT', 'UNIQUE', 'TRUNCATE', 'COMMIT', 'ROLLBACK', 'BEGIN', 'ASC', 'DESC',
        'SHOW', 'USE', 'IF EXISTS'],
      functions: [
        'COUNT|(*) or col', 'SUM|(col)', 'AVG|(col)', 'MIN|(col)', 'MAX|(col)',
        'COALESCE|(first,second)', 'IFNULL|(expr,value)', 'CONCAT|(...strings)',
        'SUBSTRING|(string,pos,len)', 'UPPER|(string)', 'LOWER|(string)', 'LENGTH|(string)', 'ROUND|(number,decimals?)',
        'NOW|()', 'DATE_FORMAT|(date,format)', 'DATEDIFF|(date1,date2)', 'CAST|(expr AS type)',
        'GROUP_CONCAT|(col)', 'RAND|()',
        'TRIM|(string)', 'REPLACE|(string,from,to)', 'LEFT|(string,n)', 'RIGHT|(string,n)',
        'ABS|(number)', 'CEIL|(number)', 'FLOOR|(number)', 'MOD|(a,b)', 'POWER|(a,b)', 'SQRT|(number)',
        'IF|(condition,a,b)', 'NULLIF|(a,b)', 'YEAR|(date)', 'MONTH|(date)', 'DAY|(date)',
        'CURDATE|()', 'CURRENT_TIMESTAMP|()'
      ],
      snippets: [
        { label: 'select-all', body: 'SELECT *\nFROM table_name\nWHERE condition;' },
        { label: 'select-cols', body: 'SELECT col1, col2\nFROM table_name\nWHERE condition\nORDER BY col1 DESC\nLIMIT 20;' },
        { label: 'insert', body: 'INSERT INTO table_name (col1, col2)\nVALUES (val1, val2);' },
        { label: 'update', body: 'UPDATE table_name\nSET col1 = val1\nWHERE condition;' },
        { label: 'delete', body: 'DELETE FROM table_name\nWHERE condition;' },
        { label: 'create-table', body: 'CREATE TABLE table_name (\n··id INT PRIMARY KEY AUTO_INCREMENT,\n··name VARCHAR(255) NOT NULL,\n··created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n);' },
        { label: 'join', body: 'SELECT a.*, b.*\nFROM table_a a\nINNER JOIN table_b b ON b.a_id = a.id\nWHERE condition;' },
        { label: 'group-count', body: 'SELECT col, COUNT(*) AS total\nFROM table_name\nGROUP BY col\nHAVING total > 1\nORDER BY total DESC;' },
        { label: 'alter-add', body: 'ALTER TABLE table_name\nADD COLUMN col_name VARCHAR(255) DEFAULT NULL;' }
      ]
    },
    /* ─────────────────────────────── SHELL ───────────────────────────── */
    shell: {
      keywords: ['if', 'then', 'else', 'elif', 'fi', 'for', 'in', 'do', 'done', 'while',
        'until', 'case', 'esac', 'function', 'return', 'exit', 'local', 'export',
        'source', 'alias', 'set', 'shift', 'read', 'trap', 'eval', 'exec', 'wait',
        'test', 'sudo', 'echo', 'printf', 'cd', 'ls', 'cp', 'mv', 'rm', 'mkdir', 'touch',
        'cat', 'chmod', 'kill', 'sleep'],
      functions: [
        'grep|-r -n -i pattern', 'find|. -name "*.ext"', 'sed|sed s/old/new/g file',
        'awk|awk {print $1} file', 'cut|-d: -f1', 'sort|-u -r -n', 'uniq|-c',
        'wc|-l -w -c', 'head|-n 20 file', 'tail|-f file', 'xargs|| xargs cmd',
        'curl|-s -X POST -d … url', 'wget|url', 'tar|-czf out.tgz dir',
        'which|cmd', 'ln|-s target link', 'du|-sh dir', 'systemctl|status/restart svc'
      ],
      snippets: [
        { label: 'shebang-bash', body: '#!/usr/bin/env bash\nset -euo pipefail\n' },
        { label: 'sh', detail: 'POSIX shebang', body: '#!/bin/sh\n' },
        { label: 'if-file', body: 'if [ -f "$file" ]; then\n··echo "exists"\nfi' },
        { label: 'if-dir', body: 'if [ -d "$dir" ]; then\n··echo "exists"\nfi' },
        { label: 'if-empty', body: 'if [ -z "$VAR" ]; then\n··echo "VAR is empty"\nfi' },
        { label: 'for-loop', body: 'for item in a b c; do\n··echo "$item"\ndone' },
        { label: 'while-read', body: 'while IFS= read -r line; do\n··echo "$line"\ndone < input.txt' },
        { label: 'case', body: 'case "$1" in\n··start) ;;\n··stop) ;;\n··*) echo "usage: $0 {start|stop}" ;;\nesac' },
        { label: 'function', body: 'my_func() {\n··echo "hello"\n}' },
        { label: 'trap-cleanup', body: "trap 'echo \"cleaning up\"; exit 1' INT TERM" }
      ]
    },
    /* ──────────────────────────────── C ─────────────────────────────── */
    c: {
      keywords: ['int', 'char', 'float', 'double', 'void', 'long', 'short', 'signed',
        'unsigned', 'struct', 'union', 'enum', 'typedef', 'const', 'static', 'extern',
        'volatile', 'return', 'if', 'else', 'switch', 'case', 'default', 'for', 'while',
        'do', 'break', 'continue', 'goto', 'sizeof', 'inline', '#include', '#define',
        '#ifdef', '#ifndef', '#endif', '#pragma', '#else'],
      functions: [
        'printf|(format,...)', 'scanf|(format,...)', 'fprintf|(file,format,...)',
        'sprintf|(buffer,format,...)', 'snprintf|(buffer,size,format,...)', 'malloc|(size)',
        'calloc|(count,size)', 'realloc|(ptr,size)', 'free|(ptr)', 'memcpy|(dest,src,n)',
        'memset|(dest,c,n)', 'strcpy|(dest,src)', 'strcmp|(a,b)==0 equal', 'strlen|(string)',
        'strchr|(string,c)', 'strstr|(haystack,needle)', 'fopen|(path,mode)',
        'fclose|(file)', 'fread|(buffer,size,count,file)', 'fgets|(buffer,n,file)', 'puts|(string)',
        'exit|(status)', 'assert|(expr)', 'atoi|(string)', 'pow|(x,y)math.h',
        'sqrt|(x)math.h', 'floor|(x)', 'ceil|(x)', 'qsort|(base,n,size,cmp)',
        'time|(NULL)', 'rand|()'
      ],
      snippets: [
        { label: 'main', body: 'int main(int argc, char *argv[]) {\n··\n··return 0;\n}' },
        { label: 'main-void', body: 'int main(void) {\n··\n··return 0;\n}' },
        { label: 'include', body: '#include <stdio.h>\n#include <stdlib.h>\n#include <string.h>\n' },
        { label: 'for', body: 'for (int i = 0; i < n; i++) {\n··\n}' },
        { label: 'while', body: 'while (cond) {\n··\n}' },
        { label: 'dowhile', body: 'do {\n··\n} while (cond);' },
        { label: 'ifelse', body: 'if (cond) {\n··\n} else {\n··\n}' },
        { label: 'switch', body: 'switch (x) {\n··case 1:\n····break;\n··default:\n····break;\n}' },
        { label: 'function', body: 'int func_name(int arg) {\n··\n··return 0;\n}' },
        { label: 'struct', body: 'struct Name {\n··int field;\n};' },
        { label: 'typedef-struct', body: 'typedef struct {\n··int field;\n} Name;' },
        { label: 'enum', body: 'enum State {\n··STATE_IDLE,\n··STATE_RUN\n};' },
        { label: 'malloc-check', body: 'int *arr = malloc(n * sizeof(int));\nif (arr == NULL) {\n··fprintf(stderr, "alloc failed\\n");\n··return 1;\n}' },
        { label: 'printf-line', body: 'printf("%d\\n", value);' }
      ]
    },
    /* ─────────────────────────────── C++ ─────────────────────────────── */
    cpp: {
      keywords: ['class', 'public', 'private', 'protected', 'virtual', 'override',
        'friend', 'namespace', 'using', 'template', 'typename', 'this', 'new', 'delete',
        'try', 'catch', 'throw', 'bool', 'true', 'false', 'nullptr', 'constexpr', 'auto',
        'explicit', 'operator', 'inline', 'mutable', 'noexcept', 'static_cast',
        'dynamic_cast', 'reinterpret_cast', 'struct', 'enum', 'typedef', 'const',
        'unsigned', 'void', 'int', 'char', 'float', 'double', 'return', 'if', 'else',
        'for', 'while', 'do', 'switch', 'case', 'default', 'break', 'continue', 'sizeof',
        'std'],
      functions: [
        'std::cout|"<<" output', 'std::cin|">>" input', 'std::endl|newline+flush',
        'std::getline|(stream,str)', 'std::vector|dynamic array', 'push_back|(value)',
        'emplace_back|(...args)', 'size|.size()', 'begin|.begin()', 'end|.end()',
        'std::sort|(first,last)', 'std::find|(first,last,value)', 'std::reverse|(first,last)',
        'std::string|string type', 'substr|(pos,len?)', 'c_str|str.c_str()',
        'make_unique|make_unique<T>(...args)', 'make_shared|make_shared<T>(...args)',
        'std::move|(x)', 'std::pair|pair<A,B>', 'std::map|ordered associative',
        'std::unordered_map|hash associative'
      ],
      snippets: [
        { label: 'cpp-main', body: '#include <iostream>\nint main() {\n··std::cout << "Hello" << std::endl;\n··return 0;\n}' },
        { label: 'includes', body: '#include <iostream>\n#include <vector>\n#include <string>\n#include <algorithm>\n' },
        { label: 'class', body: 'class Name {\npublic:\n··Name();\n··~Name();\nprivate:\n};' },
        { label: 'ctor', detail: 'constructor impl', body: 'Name::Name()\n{\n··\n}' },
        { label: 'range-for', body: 'for (const auto& item : vec) {\n··std::cout << item << std::endl;\n}' },
        { label: 'vector-init', body: 'std::vector<int> v = {1, 2, 3};' },
        { label: 'lambda', body: 'auto fn = [](int x) {\n··return x * 2;\n};' },
        { label: 'trycatch', body: 'try {\n··\n} catch (const std::exception& e) {\n··std::cerr << e.what() << std::endl;\n}' },
        { label: 'namespace', body: 'namespace name {\n}' },
        { label: 'template-fn', body: 'template <typename T>\nT func_name(T arg) {\n··return arg;\n}' },
        { label: 'unique-ptr', body: 'auto ptr = std::make_unique<Type>(args);' },
        { label: 'cout', detail: 'std::cout << … << std::endl;', body: 'std::cout << ' + CURSOR_MARKER + ' << std::endl;' },
        { label: 'cout-line', body: 'std::cout << value << std::endl;' }
      ]
    },
    /* ── Java (also offered for Kotlin/C# — closest curly-brace OO pool) ── */
    java: {
      keywords: ['public', 'private', 'protected', 'class', 'interface', 'extends',
        'implements', 'static', 'final', 'void', 'int', 'long', 'double', 'float',
        'boolean', 'char', 'byte', 'short', 'String', 'var', 'new', 'return', 'if',
        'else', 'switch', 'case', 'break', 'continue', 'for', 'while', 'do', 'try',
        'catch', 'finally', 'throw', 'throws', 'this', 'super', 'instanceof', 'enum',
        'package', 'import', 'abstract', 'synchronized', 'assert', 'default', 'null',
        'true', 'false'],
      functions: [
        'System.out.println|(x)', 'System.out.print|(x)', 'System.out.printf|(format,...)',
        'String.valueOf|(x)', 'String.format|(format,...)', 'String.join|(separator,…)',
        'equals|a.equals(b)', 'length|str.length()', 'substring|(begin,end?)',
        'indexOf|(string)', 'split|(regex)', 'trim|()', 'replace|(old,new)', 'charAt|(index)',
        'contains|(string)', 'isEmpty|()', 'toUpperCase|()', 'toLowerCase|()',
        'Integer.parseInt|(string)', 'Double.parseDouble|(string)', 'Long.parseLong|(string)',
        'Math.max|(a,b)', 'Math.min|(a,b)', 'Math.abs|(x)', 'Math.pow|(a,b)',
        'Math.sqrt|(x)', 'Math.random|()', 'Arrays.sort|(array)',
        'Arrays.toString|(array)', 'Arrays.asList|(...items)', 'List.of|(…) immutable',
        'add|list.add(item)', 'get|list.get(index)', 'set|list.set(index,value)',
        'remove|list.remove(index)', 'size|collection.size()', 'put|map.put(key,value)',
        'getOrDefault|map.getOrDefault(key,default)', 'containsKey|(key)', 'keySet|()',
        'values|()', 'entrySet|()', 'Objects.equals|(a,b) null-safe'
      ],
      snippets: [
        { label: 'class-main', detail: 'public class + main', body: 'public class Main {\n··public static void main(String[] args) {\n····\n··}\n}' },
        { label: 'psvm', detail: 'main method', body: 'public static void main(String[] args) {\n····\n}' },
        { label: 'sysout', body: 'System.out.println();' },
        { label: 'sout', detail: 'alias of sysout', body: 'System.out.println(' + CURSOR_MARKER + ');' },
        { label: 'for', body: 'for (int i = 0; i < n; i++) {\n··\n}' },
        { label: 'foreach', body: 'for (Type item : items) {\n··\n}' },
        { label: 'while', body: 'while (cond) {\n··\n}' },
        { label: 'ifelse', body: 'if (cond) {\n··\n} else {\n··\n}' },
        { label: 'trycatch', body: 'try {\n··\n} catch (Exception e) {\n··e.printStackTrace();\n}' },
        { label: 'try-resources', body: 'try (BufferedReader br = new BufferedReader(new FileReader(file))) {\n··\n}' },
        { label: 'getter-setter', body: 'public Type getX() {\n··return x;\n}\npublic void setX(Type x) {\n··this.x = x;\n}' },
        { label: 'ctor', body: 'public Name(Type arg) {\n··this.arg = arg;\n}' },
        { label: 'interface', body: 'public interface Name {\n··void method();\n}' },
        { label: 'enum', body: 'public enum Status {\n··ACTIVE,\n··INACTIVE\n}' },
        { label: 'arraylist', body: 'List<Type> items = new ArrayList<>();' },
        { label: 'hashmap', body: 'Map<Key, Value> map = new HashMap<>();' },
        { label: 'lambda', body: 'Runnable r = () -> {\n··\n};' },
        { label: 'stream-filter', body: 'List<Type> filtered = items.stream()\n··.filter(i -> i.isActive())\n··.collect(Collectors.toList());' }
      ]
    },
    /* ─────────────────────────────── GO ──────────────────────────────── */
    go: {
      keywords: ['break', 'case', 'chan', 'const', 'continue', 'default', 'defer', 'else', 'fallthrough', 'for', 'func', 'go', 'goto', 'if', 'import', 'interface', 'map', 'package', 'range', 'return', 'select', 'struct', 'switch', 'type', 'var', 'true', 'false', 'nil', 'iota', 'any'],
      functions: ['fmt.Println|(x)', 'fmt.Printf|(format,...)', 'fmt.Sprintf|(format,...)', 'len|(x)', 'cap|(x)', 'make|(type,count?)', 'new|(T)', 'append|(slice,values...)', 'copy|(dest,src)', 'delete|(map,key)', 'close|(channel)', 'panic|(value)', 'recover|()', 'strings.Contains|(s,substr)', 'strings.Split|(s,sep)', 'strings.Join|(parts,sep)', 'strings.ToUpper|(s)', 'strings.ToLower|(s)', 'strings.TrimSpace|(s)', 'strconv.Atoi|(string)→int', 'strconv.Itoa|(number)→str', 'os.Open|(path)', 'os.ReadFile|(path)', 'os.WriteFile|(path,data)', 'io.ReadAll|(reader)', 'json.Marshal|(value)', 'json.Unmarshal|(data,&v)', 'math.Abs|(x)', 'math.Max|(a,b)', 'time.Now|()', 'errors.New|(message)'],
      snippets: [
        { label: 'main', body: 'func main() {\n··\n}' },
        { label: 'func', body: 'func name(arg string) error {\n··\n}' },
        { label: 'iferr', detail: 'nil-error check', body: 'if err != nil {\n··return err\n}' },
        { label: 'for', body: 'for i := 0; i < n; i++ {\n··\n}' },
        { label: 'forrange', body: 'for i, v := range items {\n··\n}' },
        { label: 'struct', body: 'type Name struct {\n··Field string\n}' },
        { label: 'method', body: 'func (r *Receiver) name() {\n··\n}' },
        { label: 'goroutine', body: 'go func() {\n··\n}()' },
        { label: 'defer', body: 'defer func() {\n··\n}()' }
      ]
    },
    /* ─────────────────────────────── RUST ────────────────────────────── */
    rust: {
      keywords: ['as', 'async', 'await', 'break', 'const', 'continue', 'crate', 'dyn', 'else', 'enum', 'extern', 'false', 'fn', 'for', 'if', 'impl', 'in', 'let', 'loop', 'match', 'mod', 'move', 'mut', 'pub', 'ref', 'return', 'self', 'Self', 'static', 'struct', 'super', 'trait', 'true', 'type', 'unsafe', 'use', 'where', 'while', 'Some', 'None', 'Ok', 'Err'],
      functions: ['println!|(...args)', 'eprintln!|(...args)', 'format!|(format,...)', 'vec!|[...items]', 'String::from|(s)', 'push_str|(&mut self,s)', 'len|(&self)', 'is_empty|(&self)', 'iter|(&self)', 'collect|()→Vec', 'map|(closure)', 'filter|(closure)', 'unwrap|()', 'expect|(message)', 'Box::new|(value)', 'Rc::new|(value)', 'Arc::new|(value)', 'std::fs::read_to_string|(path)', 'std::fs::write|(path,s)', 'std::env::args|()'],
      snippets: [
        { label: 'main', body: 'fn main() {\n··\n}' },
        { label: 'fn', body: 'fn name(arg: &str) {\n··\n}' },
        { label: 'let', body: 'let name = value;' },
        { label: 'if', body: 'if cond {\n··\n}' },
        { label: 'match', body: 'match value {\n··Some(x) => x,\n··None => 0,\n}' },
        { label: 'struct', body: 'struct Name {\n··field: String,\n}' },
        { label: 'impl', body: 'impl Name {\n··fn new() -> Self {\n····\n··}\n}' },
        { label: 'trait', body: 'trait Name {\n··fn method(&self);\n}' },
        { label: 'for', body: 'for item in items.iter() {\n··\n}' }
      ]
    },
    /* ─────────────────────────────── RUBY ────────────────────────────── */
    ruby: {
      keywords: ['alias', 'and', 'begin', 'break', 'case', 'class', 'def', 'do', 'else', 'elsif', 'end', 'ensure', 'false', 'for', 'if', 'in', 'module', 'next', 'nil', 'not', 'or', 'redo', 'rescue', 'retry', 'return', 'self', 'super', 'then', 'true', 'undef', 'unless', 'until', 'when', 'while', 'yield', 'require', 'require_relative', 'attr_accessor', 'attr_reader', 'attr_writer'],
      functions: ['puts|(x)', 'print|(x)', 'p|(x)', 'gets|()', 'length|()', 'size|()', 'to_s|()', 'to_i|()', 'to_f|()', 'to_a|()', 'push|(value)', 'pop|()', 'map|{ }', 'each|{ }', 'select|{ }', 'reject|{ }', 'include?|(value)', 'join|(separator)', 'split|(separator)', 'upcase|()', 'downcase|()', 'strip|()', 'chomp|()', 'File.read|(path)', 'File.write|(path,s)', 'File.exist?|(path)', 'JSON.parse|(string)', 'JSON.generate|(obj)'],
      snippets: [
        { label: 'def', body: 'def name(arg)\n··\nend' },
        { label: 'class', body: 'class Name\n··def initialize(arg)\n····@arg = arg\n··end\nend' },
        { label: 'if', body: 'if cond\n··\nend' },
        { label: 'unless', body: 'unless cond\n··\nend' },
        { label: 'each', body: 'items.each do |item|\n··\nend' },
        { label: 'times', body: 'n.times do |i|\n··\nend' },
        { label: 'begin', detail: 'rescue block', body: 'begin\n··\nrescue StandardError => e\n··puts e.message\nend' }
      ]
    },
    /* ─────────────────────────────── LUA ─────────────────────────────── */
    lua: {
      keywords: ['and', 'break', 'do', 'else', 'elseif', 'end', 'false', 'for', 'function', 'goto', 'if', 'in', 'local', 'nil', 'not', 'or', 'repeat', 'return', 'then', 'true', 'until', 'while'],
      functions: ['print|(...args)', 'tostring|(value)', 'tonumber|(string)', 'type|(value)', 'pairs|(table)', 'ipairs|(table)', 'require|(module)', 'pcall|(function,...)', 'error|(message)', 'assert|(value)', 'string.format|(format,...)', 'string.sub|(s,i,j)', 'string.find|(s,pattern)', 'string.gsub|(s,pattern,repl)', 'table.insert|(table,value)', 'table.remove|(table,index)', 'table.concat|(table,separator)', 'math.floor|(x)', 'math.ceil|(x)', 'math.abs|(x)', 'math.random|()', 'os.time|()', 'io.open|(path,mode)'],
      snippets: [
        { label: 'function', body: 'function name(arg)\n··\nend' },
        { label: 'local', body: 'local name = value' },
        { label: 'if', body: 'if cond then\n··\nend' },
        { label: 'for', body: 'for i = 1, n do\n··\nend' },
        { label: 'forin', body: 'for k, v in pairs(t) do\n··\nend' },
        { label: 'while', body: 'while cond do\n··\nend' },
        { label: 'repeat', body: 'repeat\n··\nuntil cond' }
      ]
    },
    /* ─────────────────────────────── SWIFT ───────────────────────────── */
    swift: {
      keywords: ['class', 'deinit', 'enum', 'extension', 'func', 'import', 'init', 'let', 'operator', 'private', 'protocol', 'public', 'static', 'struct', 'subscript', 'typealias', 'var', 'break', 'case', 'continue', 'default', 'defer', 'do', 'else', 'fallthrough', 'for', 'guard', 'if', 'in', 'repeat', 'return', 'switch', 'where', 'while', 'as', 'catch', 'false', 'is', 'nil', 'self', 'Self', 'super', 'throw', 'throws', 'true', 'try'],
      functions: ['print|(...args)', 'String|(x)', 'Int|(x)', 'Double|(x)', 'count|()', 'isEmpty|()', 'append|(value)', 'map|(closure)', 'filter|(closure)', 'reduce|(initial,closure)', 'forEach|(closure)', 'contains|(value)', 'uppercased|()', 'lowercased|()', 'abs|(x)', 'min|(...values)', 'max|(...values)'],
      snippets: [
        { label: 'func', body: 'func name(arg: String) -> String {\n··\n}' },
        { label: 'if', body: 'if cond {\n··\n}' },
        { label: 'guard', body: 'guard cond else {\n··return\n}' },
        { label: 'for', body: 'for item in items {\n··\n}' },
        { label: 'struct', body: 'struct Name {\n··var field: String\n}' },
        { label: 'class', body: 'class Name {\n··init() {\n····\n··}\n}' }
      ]
    },
    /* ─────────────────────────────── DART ────────────────────────────── */
    dart: {
      keywords: ['abstract', 'as', 'assert', 'async', 'await', 'break', 'case', 'catch', 'class', 'const', 'continue', 'default', 'do', 'dynamic', 'else', 'enum', 'extends', 'extension', 'factory', 'false', 'final', 'finally', 'for', 'if', 'implements', 'import', 'in', 'is', 'late', 'mixin', 'new', 'null', 'on', 'operator', 'required', 'rethrow', 'return', 'set', 'static', 'super', 'switch', 'this', 'throw', 'true', 'try', 'typedef', 'var', 'void', 'while', 'with', 'yield'],
      functions: ['print|(x)', 'int.parse|(string)', 'double.parse|(string)', 'toString|()', 'length|()', 'isEmpty|()', 'isNotEmpty|()', 'add|(value)', 'remove|(value)', 'map|(closure)', 'where|(closure)', 'fold|(initial,closure)', 'forEach|(closure)', 'contains|(value)', 'toUpperCase|()', 'toLowerCase|()', 'trim|()', 'split|(separator)', 'join|(separator)', 'List.generate|(count,generator)', 'Future.delayed|(duration,callback)'],
      snippets: [
        { label: 'main', body: 'void main() {\n··\n}' },
        { label: 'func', body: 'String name(String arg) {\n··\n}' },
        { label: 'if', body: 'if (cond) {\n··\n}' },
        { label: 'for', body: 'for (var i = 0; i < n; i++) {\n··\n}' },
        { label: 'class', body: 'class Name {\n··final String field;\n··Name(this.field);\n}' },
        { label: 'stateless', detail: 'Flutter widget', body: 'class Name extends StatelessWidget {\n··const Name({super.key});\n··@override\n··Widget build(BuildContext context) {\n····\n··}\n}' }
      ]
    },
    /* ───────────────────────────── MARKDOWN ─────────────────────────── */
    markdown: {
      keywords: [],
      functions: [],
      snippets: [
        { label: 'h1', body: '# Heading' },
        { label: 'h2', body: '## Heading' },
        { label: 'h3', body: '### Heading' },
        { label: 'bold', body: '**bold text**' },
        { label: 'italic', body: '*italic text*' },
        { label: 'strike', body: '~~struck~~' },
        { label: 'code', detail: 'inline code', body: '`code`' },
        { label: 'codeblock', body: '```\ncode here\n```' },
        { label: 'codeblock-js', body: '```javascript\ncode here\n```' },
        { label: 'link', body: '[text](https://example.com)' },
        { label: 'image', body: '![alt text](image.png)' },
        { label: 'ul', body: '- item one\n- item two\n- item three' },
        { label: 'ol', body: '1. item one\n2. item two\n3. item three' },
        { label: 'task', body: '- [ ] todo\n- [x] done' },
        { label: 'quote', body: '> quoted text' },
        { label: 'table', body: '| Col 1 | Col 2 |\n| ----- | ----- |\n| a     | b     |' },
        { label: 'hr', body: '---' }
      ]
    }
  };

  /* Expand compact 'label|detail' strings into {label, detail} once. */
  (function expandCompactFunctions() {
    var keys = Object.keys(POOLS);
    for (var k = 0; k < keys.length; k++) {
      var pool = POOLS[keys[k]];
      var fns = pool.functions || [];
      var out = [];
      for (var i = 0; i < fns.length; i++) {
        var s = fns[i];
        if (typeof s !== 'string') { out.push(s); continue; }
        var bar = s.indexOf('|');
        if (bar === -1) out.push({ label: s });
        else out.push({ label: s.slice(0, bar), detail: s.slice(bar + 1) || undefined });
      }
      pool.functions = out;
    }
  })();

  /* Workshop / community packages land here via registerPool(). */
  var EXTRA_POOLS = {};
  var MERGED_CACHE = {};

  /* ═══════════════════════════════════════════════════════════════
  BOILERPLATE LIBRARY (full-file starters)
  ═══════════════════════════════════════════════════════════════ */
  var BOILERPLATES = {
    html: [
      {
        label: 'html5', trigger: '!', detail: 'Full HTML5 page skeleton',
        body: '<!DOCTYPE html>\n<html lang="en">\n<head>\n··<meta charset="UTF-8">\n··<meta name="viewport" content="width=device-width, initial-scale=1.0">\n··<title>Document</title>\n</head>\n<body>\n··\n</body>\n</html>'
      },
      {
        label: 'html5-linked', detail: 'Page + linked style.css & app.js',
        body: '<!DOCTYPE html>\n<html lang="en">\n<head>\n··<meta charset="UTF-8">\n··<meta name="viewport" content="width=device-width, initial-scale=1.0">\n··<title>Document</title>\n··<link rel="stylesheet" href="style.css">\n</head>\n<body>\n··\n··<script src="app.js"></script>\n</body>\n</html>'
      }
    ],
    css: [
      {
        label: 'css-reset', trigger: '*', detail: 'Universal box-sizing reset',
        body: '*, *::before, *::after {\n··box-sizing: border-box;\n··margin: 0;\n··padding: 0;\n}'
      },
      {
        label: 'css-starter', detail: 'Reset + :root variables + body',
        body: '*, *::before, *::after {\n··box-sizing: border-box;\n··margin: 0;\n··padding: 0;\n}\n:root {\n··--accent: #f5a524;\n··--bg: #0d1420;\n··--text: #e8eef7;\n}\nbody {\n··font-family: system-ui, sans-serif;\n··background: var(--bg);\n··color: var(--text);\n··line-height: 1.6;\n}'
      }
    ],
    javascript: [
      {
        label: 'node-script', trigger: '#', detail: 'Node script with main()',
        body: "#!/usr/bin/env node\n'use strict';\nfunction main() {\n··\n}\nmain();"
      },
      {
        label: 'iife', detail: 'Immediately-invoked wrapper',
        body: "(function () {\n··'use strict';\n··\n})();"
      },
      {
        label: 'dom-ready', detail: 'DOMContentLoaded starter',
        body: "document.addEventListener('DOMContentLoaded', function () {\n··\n});"
      }
    ],
    php: [
      {
        label: 'php-page', detail: 'Full PHP page (PHP top + HTML shell)',
        body: "<?php\ndeclare(strict_types=1);\n$title = 'Home';\n?>\n<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n··<meta charset=\"UTF-8\">\n··<title><?php echo htmlspecialchars($title); ?></title>\n</head>\n<body>\n··\n</body>\n</html>"
      },
      {
        label: 'php-class-file', detail: 'strict_types + namespace + class',
        body: '<?php\ndeclare(strict_types=1);\nnamespace App;\nclass Name\n{\n··public function __construct()\n··{\n····\n··}\n}'
      },
      {
        label: 'php-api-json', detail: 'JSON API endpoint starter',
        body: "<?php\ndeclare(strict_types=1);\nheader('Content-Type: application/json');\n$data = [];\necho json_encode($data);\nexit;"
      }
    ],
    python: [
      {
        label: 'py-script', trigger: '#', detail: 'Shebang + docstring + main guard',
        body: "#!/usr/bin/env python3\n\"\"\"Module docstring.\"\"\"\ndef main():\n··pass\nif __name__ == '__main__':\n··main()"
      }
    ],
    shell: [
      {
        label: 'bash-strict', trigger: '#', detail: 'bash + set -euo pipefail',
        body: '#!/usr/bin/env bash\nset -euo pipefail\n'
      },
      { label: 'sh-plain', detail: 'POSIX sh header', body: '#!/bin/sh\n' }
    ],
    c: [
      {
        label: 'c-program', detail: 'Includes + main()',
        body: '#include <stdio.h>\n#include <stdlib.h>\nint main(void)\n{\n··\n··return 0;\n}'
      }
    ],
    cpp: [
      {
        label: 'cpp-program', detail: 'iostream + main()',
        body: '#include <iostream>\nint main()\n{\n··std::cout << "Hello" << std::endl;\n··return 0;\n}'
      }
    ],
    java: [
      {
        label: 'java-main', detail: 'public class Main + main',
        body: 'public class Main {\n··public static void main(String[] args) {\n····\n··}\n}'
      }
    ],
    sql: [
      {
        label: 'sql-header', detail: 'Foreign keys + table starter',
        body: 'PRAGMA foreign_keys = ON;\nCREATE TABLE IF NOT EXISTS items (\n··id INTEGER PRIMARY KEY,\n··name TEXT NOT NULL\n);'
      }
    ],
    markdown: [
      { label: 'doc', detail: 'Title + sections', body: '# Title\n## Overview\n··\n## Notes\n' }
    ]
  };

  function boilerplatesFor(id) {
    if (!id) return [];
    var aliases = {
      typescript: 'javascript', scss: 'css', sass: 'css', less: 'css',
      'php+html': 'php', csharp: 'java', kotlin: 'java', bash: 'shell'
    };
    return BOILERPLATES[id] || BOILERPLATES[aliases[id]] || [];
  }

  /* One-time cleanup: a pool snippet that shares a label with a boilerplate
  would show twice — remove the pool copy, the boilerplate wins. */
  (function dedupeBoilerplates() {
    var langs = Object.keys(BOILERPLATES);
    for (var i = 0; i < langs.length; i++) {
      var pool = POOLS[langs[i]];
      if (!pool || !pool.snippets) continue;
      var labels = {};
      var bps = BOILERPLATES[langs[i]];
      for (var j = 0; j < bps.length; j++) labels[bps[j].label] = true;
      pool.snippets = pool.snippets.filter(function (s) {
        return !(s && labels[s.label]);
      });
    }
  })();

  /* ═══════════════════════════════════════════════════════════════
  MODULE STATE
  ═══════════════════════════════════════════════════════════════ */
  var featureOn = !!(window.IDE_CONFIG && window.IDE_CONFIG.features &&
    window.IDE_CONFIG.features.autocomplete);
  var enabled = true;
  var autoPopup = (function () {
    try {
      var raw = localStorage.getItem(LS_AUTO_POPUP);
      if (raw === null) return true;
      return raw === '1' || raw === 'true';
    } catch (e) { return true; }
  })();
  var cmRef = null;
  var fileLangId = null;
  var debounceTimer = null;
  var composing = false;
  var docWordCache = { key: null, words: [] };
  var docSymbolCache = { key: null, symbols: [] };
  var lastChangeText = '';
  var jumpStops = [];

  /* ── learning ranking ── */
  var recentPicks = loadRecent();
  function loadRecent() {
    try {
      var r = JSON.parse(localStorage.getItem(LS_RECENT) || '{}');
      return (r && typeof r === 'object') ? r : {};
    } catch (e) { return {}; }
  }
  function saveRecent() {
    try { localStorage.setItem(LS_RECENT, JSON.stringify(recentPicks)); } catch (e) { }
  }
  function bumpRecent(lang, label) {
    var k = lang + ':' + label;
    var e = recentPicks[k] || (recentPicks[k] = { n: 0, t: 0 });
    e.n = Math.min(50, e.n + 1);
    e.t = Date.now();
    var keys = Object.keys(recentPicks);
    if (keys.length > 300) {
      var worst = null, wt = Infinity;
      for (var i = 0; i < keys.length; i++) {
        if (recentPicks[keys[i]].t < wt) { wt = recentPicks[keys[i]].t; worst = keys[i]; }
      }
      if (worst) delete recentPicks[worst];
    }
    saveRecent();
  }
  function recentBoost(lang, label) {
    var e = recentPicks[lang + ':' + label];
    if (!e || Date.now() - e.t > 14 * 86400000) return 0;
    return Math.min(18, e.n * 3);
  }

  /* ═══════════════════════════════════════════════════════════════
  POOL ACCESS + WORKSHOP BRIDGE
  ═══════════════════════════════════════════════════════════════ */
  var EMPTY_POOL = { keywords: [], functions: [], snippets: [], variables: [] };
  function poolFor(id) {
    if (!id) return EMPTY_POOL;
    if (MERGED_CACHE[id]) return MERGED_CACHE[id];
    if (id === 'php+html') {
      var a = poolFor('php');
      var b = poolFor('html');
      var both = {
        keywords: a.keywords.concat(b.keywords),
        functions: a.functions.concat(b.functions),
        snippets: a.snippets.concat(b.snippets),
        variables: a.variables.concat(b.variables)
      };
      MERGED_CACHE[id] = both;
      return both;
    }
    var base = POOLS[id];
    var extra = EXTRA_POOLS[id];
    if (!extra) return base || EMPTY_POOL;
    var merged = {
      keywords: ((base && base.keywords) || []).concat(extra.keywords || []),
      functions: ((base && base.functions) || []).concat(extra.functions || []),
      snippets: ((base && base.snippets) || []).concat(extra.snippets || []),
      variables: ((base && base.variables) || []).concat(extra.variables || [])
    };
    MERGED_CACHE[id] = merged;
    return merged;
  }
  function registerPool(langId, items) {
    if (!langId || typeof langId !== 'string' || !items || typeof items !== 'object') return;
    var slot = EXTRA_POOLS[langId];
    if (!slot) slot = EXTRA_POOLS[langId] = { keywords: [], functions: [], snippets: [], variables: [] };
    function absorb(key) {
      var arr = items[key];
      if (Array.isArray(arr)) {
        for (var i = 0; i < arr.length; i++) slot[key].push(arr[i]);
      }
    }
    absorb('keywords'); absorb('functions'); absorb('snippets'); absorb('variables');
    delete MERGED_CACHE[langId];
    if (langId === 'php' || langId === 'html') delete MERGED_CACHE['php+html'];
  }

  /* ═══════════════════════════════════════════════════════════════
  CURSOR-LANGUAGE RESOLUTION
  ═══════════════════════════════════════════════════════════════ */
  function lastTagOpen(lc, tag) {
    var needle = '<' + tag;
    var found = -1;
    var p = 0;
    while (true) {
      var i = lc.indexOf(needle, p);
      if (i === -1) break;
      var nxt = lc.charAt(i + needle.length);
      if (nxt === ' ' || nxt === '>' || nxt === '\t' || nxt === '\n' || nxt === '\r' || nxt === '/') found = i;
      p = i + 1;
      if (p >= lc.length) break;
    }
    return found;
  }
  function liveFileLangId(cm) {
    try {
      if (cm && typeof cm.getLangId === 'function' && cm.getLangId()) return cm.getLangId();
    } catch (e) { }
    return fileLangId;
  }
  function resolveCursorLang(cm) {
    var fid = liveFileLangId(cm) || '';
    if (fid === 'php' || fid === 'html') {
      var cur = null;
      try { cur = cm.getCursor(); } catch (e) { return 'html'; }
      var fromLine = Math.max(0, cur.line - 80);
      var chunk = '';
      try { chunk = cm.getRange({ line: fromLine, ch: 0 }, cur); } catch (e) { chunk = ''; }
      if (chunk.length > 24000) chunk = chunk.slice(chunk.length - 24000);
      var lc = chunk.toLowerCase();
      var phpOpen = Math.max(lc.lastIndexOf('<?php'), lc.lastIndexOf('<?='));
      var phpClose = lc.lastIndexOf('?>');
      var sOpen = lastTagOpen(lc, 'script');
      var sClose = lc.lastIndexOf('</script');
      var stOpen = lastTagOpen(lc, 'style');
      var stClose = lc.lastIndexOf('</style');
      var best = 'html';
      var bestIdx = -1;
      if (phpOpen > -1 && phpOpen > phpClose && phpOpen > bestIdx) { best = 'php'; bestIdx = phpOpen; }
      if (sOpen > -1 && sOpen > sClose && sOpen > bestIdx) { best = 'javascript'; bestIdx = sOpen; }
      if (stOpen > -1 && stOpen > stClose && stOpen > bestIdx) { best = 'css'; bestIdx = stOpen; }
      if (best === 'html' && fid === 'php') return 'php+html';
      return best;
    }
    switch (fid) {
      case 'javascript':
      case 'typescript':
        return 'javascript';
      case 'scss':
      case 'sass':
      case 'less':
        return 'css';
      case 'kotlin':
      case 'csharp':
        return 'java';
      case 'json':
        return '';
      default:
        return fid;
    }
  }

  /* ═══════════════════════════════════════════════════════════════
  SMALL HELPERS
  ═══════════════════════════════════════════════════════════════ */
  function esc(s) {
    if (U && U.escapeHtml) return U.escapeHtml(s);
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function indentUnitOf(cm) {
    try {
      if (cm.getOption('indentWithTabs')) return '\t';
      var n = parseInt(cm.getOption('indentUnit'), 10) ||
        parseInt(cm.getOption('tabSize'), 10) || 2;
      return new Array(n + 1).join(' ');
    } catch (e) { return '  '; }
  }
  function baseIndentOf(textBefore) {
    var m = /^[ \t]*/.exec(textBefore || '');
    return m ? m[0] : '';
  }
  function expandBody(body, cm, textBefore) {
    var unit = indentUnitOf(cm);
    var base = baseIndentOf(textBefore);
    var s = String(body == null ? '' : body);
    if (s.indexOf(CURSOR_MARKER) === -1) {
      var lines = s.split('\n');
      for (var i = 0; i < lines.length; i++) {
        if (lines[i].length > 0 && lines[i].split(MIDOT).join('') === '') {
          lines[i] = lines[i] + CURSOR_MARKER;
          break;
        }
      }
      s = lines.join('\n');
    }
    return s.split('\n').join('\n' + base).split(MIDOT).join(unit);
  }
  function wrapperOf(cm) {
    try { if (cm.getWrapperElement) return cm.getWrapperElement(); } catch (e) { }
    try { if (cm.view && cm.view.dom) return cm.view.dom; } catch (e) { }
    return null;
  }
  function scrollerOf(cm) {
    try { if (cm.getScrollerElement) return cm.getScrollerElement(); } catch (e) { }
    try { if (cm.view && cm.view.scrollDOM) return cm.view.scrollDOM; } catch (e) { }
    return null;
  }
  function cursorClientCoords(cm) {
    try {
      if (cm.view && cm.view.coordsAtPos) {
        var r = cm.view.coordsAtPos(cm.view.state.selection.main.head);
        if (r) return { left: r.left, top: r.top, bottom: r.bottom };
      }
    } catch (e) { }
    try {
      var c = cm.cursorCoords(true, 'window');
      if (c) return { left: c.left, top: c.top, bottom: c.bottom };
    } catch (e) { }
    try {
      var p = cm.cursorCoords(true, 'page');
      return { left: p.left - (window.scrollX || 0), top: p.top - (window.scrollY || 0), bottom: p.bottom - (window.scrollY || 0) };
    } catch (e) { }
    return null;
  }
  function inCommentOrString(cm, pos) {
    try {
      if (cm.getTokenAt) {
        var t = cm.getTokenAt(pos, true);
        if (t && t.type && /comment|string|quote/.test(t.type)) return true;
        return false;
      }
    } catch (e) { }
    try {
      var line = cm.getLine(pos.line) || '';
      var s = line.slice(0, Math.min(pos.ch, line.length));
      var inS = null, i = 0;
      while (i < s.length) {
        var c = s.charAt(i);
        if (inS) { if (c === '\\') i++; else if (c === inS) inS = null; }
        else if (c === '"' || c === "'" || c === '`') inS = c;
        else if (c === '/' && s.charAt(i + 1) === '/') return true;
        else if (c === '/' && s.charAt(i + 1) === '*') {
          if (s.indexOf('*/', i + 2) === -1) return true;
          i = s.indexOf('*/', i + 2) + 1;
        }
        i++;
      }
      if (inS) return true;
    } catch (e) { }
    return false;
  }

  /* ═══════════════════════════════════════════════════════════════
  ★ v11 COMPLETE CALL BUILDER
  Parses the pool detail "(a,b?)" into real placeholders and builds
  a full insertion: name($a, $b); — with caret at the first param,
  Tab-stops for the rest, and a SMART semicolon token that only
  becomes ";" when the call sits at the end of the line.
  ═══════════════════════════════════════════════════════════════ */
  function extractParams(detail) {
    if (!detail || typeof detail !== 'string' || detail.charAt(0) !== '(') return null;
    var close = detail.indexOf(')');
    if (close < 0) return null;
    var inner = detail.slice(1, close).trim();
    if (!inner) return null;
    var parts = inner.split(',');
    var out = [];
    for (var i = 0; i < parts.length; i++) {
      var t = parts[i].trim();
      if (!t) continue;
      if (/^\.{3}/.test(t) || /^\*/.test(t)) continue;       // variadic / *args
      t = t.replace(/^[&$]+/, '').replace(/\?$/, '').trim(); // &ref, $var, optional
      if (!/^[A-Za-z_$][\w$]{0,14}$/.test(t)) return null;   // weird → no preview
      out.push(t);
    }
    return out.length ? out : null;
  }
  function placeholderFor(name, lang) {
    if (lang === 'php' && name.charAt(0) !== '$') return '$' + name;
    return name;
  }
  function buildFunctionInsertion(label, detail, lang) {
    if (!/^[A-Za-z_$][A-Za-z0-9_$]*$/.test(label)) return null; // dotted/ns names stay bare
    var semi = SEMI_LANGS[lang] ? SEMI_TOKEN : '';
    var params = extractParams(detail);
    if (!params) return label + '(' + CURSOR_MARKER + ')' + semi;
    var inner = '';
    for (var i = 0; i < params.length; i++) {
      if (i > 0) inner += ', ';
      inner += (i === 0 ? CURSOR_MARKER : TAB_STOP) + placeholderFor(params[i], lang);
    }
    return label + '(' + inner + ')' + semi;
  }

  /* Select the placeholder word at a position (CM5 path; CM6 falls back
  to a bare caret unless editor-adapter's setSelection patch is applied). */
  function selectWordAt(cm, line, ch) {
    try {
      if (typeof cm.setSelection !== 'function') return;
      var ln = cm.getLine(line) || '';
      var re = /[\w$]+/g, m;
      while ((m = re.exec(ln))) {
        if (m.index === ch || (m.index < ch && ch <= m.index + m[0].length)) {
          if (ch > m.index && ch !== m.index) continue;
          cm.setSelection({ line: line, ch: m.index }, { line: line, ch: m.index + m[0].length });
          return;
        }
      }
    } catch (e) { }
  }
  function selectWordAtCaret(cm) {
    try {
      var cur = cm.getCursor();
      selectWordAt(cm, cur.line, cur.ch);
    } catch (e) { }
  }

  /* ═══════════════════════════════════════════════════════════════
  DOC-WORD + DOC-SYMBOL COLLECTION
  ═══════════════════════════════════════════════════════════════ */
  function collectDocWords(cm) {
    var text = '';
    try { text = cm.getValue(); } catch (e) { return []; }
    if (!text || text.length > DOC_WORD_MAX_BYTES) return [];
    if (docWordCache.key === text) return docWordCache.words;
    var seen = {};
    var out = [];
    var re = /[A-Za-z_$][\w$]{2,}/g;
    var m;
    while ((m = re.exec(text)) !== null && out.length < DOC_WORD_LIMIT) {
      if (!seen[m[0]]) { seen[m[0]] = 1; out.push(m[0]); }
      if (m.index === re.lastIndex) re.lastIndex++;
    }
    docWordCache = { key: text, words: out };
    return out;
  }

  /* ═══════════════════════════════════════════════════════════════
  MATCHING — exact > prefix > boundary substring > subsequence
  ═══════════════════════════════════════════════════════════════ */
  function matchScore(label, q) {
    if (!q) return 1;
    var raw = String(label);
    var l = raw.toLowerCase();
    q = q.toLowerCase();
    if (l === q) return 100;
    var caseBonus = raw.indexOf(q) >= 0 ? 4 : 0;
    if (l.indexOf(q) === 0) {
      return 80 + Math.max(0, 12 - (l.length - q.length)) + caseBonus;
    }
    var sub = l.indexOf(q);
    if (sub > 0) {
      var boundary = /[^a-z0-9]/.test(l.charAt(sub - 1));
      return 55 - Math.min(20, sub) + (boundary ? 8 : 0) + caseBonus;
    }
    var qi = 0, sc = 0, run = 0, last = -2;
    for (var i = 0; i < l.length && qi < q.length; i++) {
      if (l.charAt(i) === q.charAt(qi)) {
        var isB = i === 0 || /[^a-z0-9]/.test(l.charAt(i - 1)) ||
          (raw.charAt(i) !== raw.charAt(i).toLowerCase());
        run = (i === last + 1) ? run + 1 : 1;
        sc += (isB ? 7 : 1) + Math.min(6, (run - 1) * 2);
        last = i;
        qi++;
      }
    }
    if (qi === q.length) {
      var spanPenalty = Math.min(8, Math.max(0, (last - q.length) / 3));
      return Math.max(2, Math.min(34, sc - spanPenalty));
    }
    return -1;
  }

  /* ── context-aware extras (C/C++ includes, Python imports) ── */
  var C_HEADERS = ['assert.h', 'ctype.h', 'errno.h', 'float.h', 'limits.h', 'locale.h', 'math.h', 'setjmp.h', 'signal.h', 'stdarg.h', 'stddef.h', 'stdio.h', 'stdlib.h', 'string.h', 'time.h'];
  var CPP_HEADERS = ['algorithm', 'array', 'bitset', 'cassert', 'cctype', 'cmath', 'cstdio', 'cstdlib', 'cstring', 'deque', 'fstream', 'functional', 'iomanip', 'iostream', 'limits', 'list', 'map', 'memory', 'numeric', 'queue', 'set', 'sstream', 'stack', 'stdexcept', 'string', 'unordered_map', 'unordered_set', 'utility', 'vector'];
  var PY_MODULES = ['os', 'sys', 'json', 'math', 'random', 'datetime', 're', 'io', 'time', 'collections', 'itertools', 'functools', 'pathlib', 'subprocess', 'typing', 'dataclasses', 'argparse', 'logging', 'hashlib', 'base64', 'copy', 'csv', 'sqlite3', 'threading', 'urllib'];

  function contextHints(eff, textBefore, word) {
    if (!textBefore) return null;
    var out = [], i, lbl;
    if (eff === 'c' || eff === 'cpp') {
      var m = /#\s*include\s*([<"])([^<">]*)$/.exec(textBefore);
      if (m) {
        var close = (m[1] === '<') ? '>' : '"';
        var src = (eff === 'c') ? C_HEADERS : CPP_HEADERS;
        for (i = 0; i < src.length; i++) {
          lbl = src[i];
          out.push({ label: lbl, insert: lbl + close, detail: '#include', type: 'module' });
        }
        return out;
      }
    }
    if (eff === 'python') {
      var mi = /(^|\n)\s*(?:from|import)\s+[A-Za-z0-9_.,\s]*$/.exec(textBefore);
      if (mi) {
        for (i = 0; i < PY_MODULES.length; i++) {
          lbl = PY_MODULES[i];
          out.push({ label: lbl, insert: lbl, detail: 'module', type: 'module' });
        }
        return out;
      }
    }
    return null;
  }

  /* ═══════════════════════════════════════════════════════════════
  v17 SMART CONTEXT (attribute / member / CSS value detection +
  symbols defined in THIS file + installable pools)
  ═══════════════════════════════════════════════════════════════ */
  function htmlAttrContext(lang, before) {
    if (lang !== 'html' && lang !== 'php+html') return false;
    var open = before.lastIndexOf('<');
    if (open < 0) return false;
    if (before.indexOf('>', open) >= 0) return false;
    if (!/^[a-zA-Z][\w-]*/.test(before.slice(open + 1))) return false;
    return /\s/.test(before.slice(open));
  }
  function memberContext(lang, before) {
    if (lang === 'php' || lang === 'php+html') {
      if (/(->|::)\s*[\w$]*$/.test(before)) return true;
    }
    if (lang === 'javascript') {
      if (/\.\s*[\w$]*$/.test(before) && !/[\d.]\s*[\w$]*$/.test(before)) return true;
    }
    return false;
  }
  function cssValueContext(lang, lineText, ch, pool) {
    if (lang !== 'css') return null;
    var before = lineText.slice(0, ch);
    var m = /(^|[;{]\s*)([a-z-]+)\s*:\s*[^;]*$/.exec(before);
    if (!m) return null;
    var prop = m[2];
    var fns = pool.functions || [];
    for (var i = 0; i < fns.length; i++) {
      if (fns[i].label === prop && fns[i].detail) {
        var parts = String(fns[i].detail).split('·');
        var out = [];
        for (var v = 0; v < parts.length; v++) {
          var val = parts[v].trim();
          if (val) out.push(val);
        }
        return out;
      }
    }
    return null;
  }

  var SYMBOL_RULES = [
    { kind: 'function', type: 'function', re: /\bfunction\s+([A-Za-z_$][\w$]*)\s*\(/g },
    { kind: 'method', type: 'function', re: /\b(?:public|private|protected)\s+(?:static\s+)?function\s+([A-Za-z_$][\w$]*)\s*\(/g },
    { kind: 'class', type: 'keyword', re: /\b(?:class|interface|trait|struct|enum)\s+([A-Za-z_$][\w$]*)/g },
    { kind: 'function', type: 'function', re: /\bdef\s+([A-Za-z_]\w*)\s*\(/g },
    { kind: 'constant', type: 'variable', re: /\bconst\s+([A-Za-z_$][\w$]*)\s*=/g },
    { kind: 'variable', type: 'variable', re: /\b(?:let|var)\s+([A-Za-z_$][\w$]*)\s*=/g },
    { kind: 'variable', type: 'variable', re: /(^|[^\w$])\$([A-Za-z_]\w*)\s*=/g, group: 2, prefix: '$' },
    { kind: 'constant', type: 'variable', re: /#define\s+([A-Za-z_]\w*)/g }
  ];
  function collectDocSymbols(cm) {
    var text = '';
    try { text = cm.getValue(); } catch (e) { return []; }
    if (!text || text.length > DOC_WORD_MAX_BYTES) return [];
    if (docSymbolCache.key === text) return docSymbolCache.symbols;
    var seen = {};
    var out = [];
    for (var r = 0; r < SYMBOL_RULES.length; r++) {
      var rule = SYMBOL_RULES[r];
      rule.re.lastIndex = 0;
      var m;
      while ((m = rule.re.exec(text)) !== null && out.length < 300) {
        var gi = rule.group || 1;
        var name = m[gi];
        if (!name || seen[name]) continue;
        seen[name] = 1;
        out.push({
          label: (rule.prefix || '') + name,
          type: rule.type,
          detail: rule.kind + ' · this file'
        });
        if (m.index === rule.re.lastIndex) rule.re.lastIndex++;
      }
    }
    docSymbolCache = { key: text, symbols: out };
    return out;
  }

  function loadStoredPools() {
    try {
      var raw = localStorage.getItem(LS_POOLS);
      if (!raw) return;
      var data = JSON.parse(raw);
      if (!data || typeof data !== 'object') return;
      for (var lang in data) {
        if (Object.prototype.hasOwnProperty.call(data, lang)) {
          registerPool(lang, data[lang]);
        }
      }
    } catch (e) { }
  }
  function installPool(lang, items, persist) {
    if (!lang || !items) return;
    registerPool(lang, items);
    if (persist) {
      try {
        var data = JSON.parse(localStorage.getItem(LS_POOLS) || '{}');
        var slot = data[lang] || (data[lang] = { keywords: [], functions: [], snippets: [], variables: [] });
        ['keywords', 'functions', 'snippets', 'variables'].forEach(function (k) {
          if (Object.prototype.toString.call(items[k]) === '[object Array]') {
            slot[k] = slot[k].concat(items[k]);
          }
        });
        localStorage.setItem(LS_POOLS, JSON.stringify(data));
      } catch (e) { }
    }
  }

  /* ═══════════════════════════════════════════════════════════════
  COMPUTE — the single ranking core
  ★ v11 ordering contract:
    empty doc  → boilerplates (group 0), then snippets, then the rest
    coded doc  → snippets (group 0), boilerplates below them, rest after
  ═══════════════════════════════════════════════════════════════ */
  function computeCompletions(cm, manual) {
    if (!cm) return null;
    var cur; try { cur = cm.getCursor(); } catch (e) { return null; }
    var lineText = ''; try { lineText = cm.getLine(cur.line) || ''; } catch (e) { }
    var end = Math.min(cur.ch, lineText.length), start = end;
    while (start > 0 && /[\w$]/.test(lineText.charAt(start - 1))) start--;
    var word = lineText.slice(start, end);

    /* ★ v11.1 FIX — "empty" must mean empty BEFORE your keystroke.
   Remove the word you are typing right now, then test what is left.
   The old check counted your own typed character as content, so a
   brand-new file flipped to "snippet-first" the moment you typed a
   single letter — burying the boilerplates below the snippets. */
    var docTextFull = '';
    try { docTextFull = cm.getValue(); } catch (e2) { docTextFull = ''; }
    var docLines = docTextFull.split('\n');
    if (cur.line < docLines.length) {
      docLines[cur.line] = lineText.slice(0, start) + lineText.slice(end);
    }
    var docEmpty = !/[^\s]/.test(docLines.join('\n'));

    var eff = resolveCursorLang(cm);
    var beforeCursor = lineText.slice(0, cur.ch);
    var ctxItems = contextHints(eff, beforeCursor, word);
    var inAttr = htmlAttrContext(eff, beforeCursor);
    var inMember = memberContext(eff, beforeCursor);
    var pool = poolFor(eff);
    var cssVals = cssValueContext(eff, lineText, cur.ch, pool);

    if (!manual && word.length < 1 && !(ctxItems && ctxItems.length) &&
      !inAttr && !(cssVals && cssVals.length)) return null;

    if (cssVals && cssVals.length) {
      var vItems = [];
      for (var vi = 0; vi < cssVals.length && vItems.length < MAX_RESULTS; vi++) {
        if (word && matchScore(cssVals[vi], word) < 0) continue;
        vItems.push({ label: cssVals[vi], insert: cssVals[vi], detail: 'value', type: 'text' });
      }
      if (vItems.length) {
        return { list: vItems, word: word, lang: eff, line: cur.line, start: start, end: end, docEmpty: docEmpty };
      }
    }

    var scored = [];
    var seen = {};
    function consider(label, insert, detail, type, boost) {
      if (!label || seen[label]) return;
      var sc = matchScore(label, word);
      if (sc < 0) return;
      seen[label] = 1;
      scored.push({
        score: sc + (boost || 0) + recentBoost(eff, label),
        item: { label: label, insert: insert, detail: detail, type: type }
      });
    }

    var arr, i, entry, lbl;
    if (ctxItems) {
      for (i = 0; i < ctxItems.length; i++) {
        consider(ctxItems[i].label, ctxItems[i].insert, ctxItems[i].detail, ctxItems[i].type || 'module', 60);
      }
    }

    /* ★ v11: group boosts — the hard ordering comes from groupRank below,
       these boosts only order INSIDE each group. */
    var bpBoost = docEmpty ? 72 : 6;
    var snBoost = docEmpty ? 12 : 30;

    arr = (inMember || inAttr) ? [] : (pool.snippets || []);
    for (i = 0; i < arr.length; i++) {
      entry = arr[i];
      if (!entry || !entry.label) continue;
      consider(entry.label, expandBody(entry.body, cm, lineText.slice(0, cur.ch)), entry.detail || 'snippet', 'snippet', snBoost);
    }
    arr = (inMember || inAttr) ? [] : boilerplatesFor(eff);
    for (i = 0; i < arr.length; i++) {
      entry = arr[i];
      if (!entry || !entry.label) continue;
      consider(entry.label, expandBody(entry.body, cm, lineText.slice(0, cur.ch)), entry.detail || 'boilerplate', 'boilerplate', bpBoost);
    }

    /* ★ v11 functions: complete call skeletons with params + smart ";" */
    arr = pool.functions || [];
    for (i = 0; i < arr.length; i++) {
      entry = arr[i];
      if (!entry || !entry.label) continue;
      var fnInsert = buildFunctionInsertion(entry.label, entry.detail, eff);
      consider(entry.label, fnInsert, entry.detail || 'function', 'function', 15);
    }

    arr = inMember ? [] : (pool.keywords || []);
    for (i = 0; i < arr.length; i++) {
      lbl = arr[i];
      if (!lbl) continue;
      consider(String(lbl), null, null, 'keyword', 8 + (inAttr ? 30 : 0));
    }
    arr = pool.variables || [];
    for (i = 0; i < arr.length; i++) {
      lbl = arr[i];
      if (!lbl) continue;
      consider(String(lbl), null, null, 'variable', 10);
    }

    var docSyms = collectDocSymbols(cm);
    for (i = 0; i < docSyms.length; i++) {
      consider(docSyms[i].label, null, docSyms[i].detail, docSyms[i].type,
        docSyms[i].type === 'function' ? 34 : 22);
    }

    if (word && word.length >= 2 && !inMember) {
      var words = collectDocWords(cm);
      for (i = 0; i < words.length; i++) consider(words[i], null, null, 'text', 0);
    }

    if (!scored.length) return null;

    var typeRank = { snippet: 0, function: 1, keyword: 2, variable: 3, module: 4, text: 5 };
    var groupRank = docEmpty
      ? { boilerplate: 0, snippet: 1, function: 2, keyword: 3, variable: 4, module: 5, text: 6 }
      : { snippet: 0, function: 1, boilerplate: 2, keyword: 3, variable: 4, module: 5, text: 6 };

    scored.sort(function (a, b) {
      var ga = groupRank[a.item.type] != null ? groupRank[a.item.type] : 7;
      var gb = groupRank[b.item.type] != null ? groupRank[b.item.type] : 7;
      if (ga !== gb) return ga - gb;
      if (b.score !== a.score) return b.score - a.score;
      var ra = typeRank[a.item.type] != null ? typeRank[a.item.type] : 6;
      var rb = typeRank[b.item.type] != null ? typeRank[b.item.type] : 6;
      if (ra !== rb) return ra - rb;
      return a.item.label.length - b.item.label.length;
    });

    var cap = (manual && !word) ? MANUAL_RESULTS : MAX_RESULTS;
    var list = [];
    for (i = 0; i < scored.length && list.length < cap; i++) {
      var it = scored[i].item;
      list.push({
        label: it.label,
        insert: (it.insert != null) ? it.insert : it.label,
        detail: it.detail,
        type: it.type
      });
    }
    if (!list.length) return null;
    return { list: list, word: word, lang: eff, line: cur.line, start: start, end: end, docEmpty: docEmpty };
  }

  /* ═══════════════════════════════════════════════════════════════
  SMART ABSORPTION
  ═══════════════════════════════════════════════════════════════ */
  function absorbPrefix(before, insert) {
    if (!before || !insert) return 0;
    var max = Math.min(before.length, insert.length, 40);
    for (var n = max; n >= 2; n--) {
      if (before.slice(before.length - n) === insert.slice(0, n)) return n;
    }
    if (before.charAt(before.length - 1) === '<' && insert.charAt(0) === '<') return 1;
    return 0;
  }

  /* ═══════════════════════════════════════════════════════════════
  IME-SAFE INSERT HELPERS (v15.1 / v16.1 retained)
  ═══════════════════════════════════════════════════════════════ */
  function imeCommit(cm) {
    try {
      if (!composing) return;
      var ae = document.activeElement;
      var wrap = wrapperOf(cm);
      if (ae && ae !== document.body && wrap && wrap.contains(ae)) ae.blur();
    } catch (e) { }
  }
  function imeHardReset(cm) {
    try {
      var inApp = !!(window.QuirkyIme || window.QuirkyStorage ||
        window.QuirkyFiles || window.AndroidHardware);
      if (!inApp) return false;
      var wrap = wrapperOf(cm);
      var ae = document.activeElement;
      if (!wrap || !ae || ae === document.body || !wrap.contains(ae)) return false;
      ae.blur();
      return true;
    } catch (e) { return false; }
  }
  function settleCaret(cm) {
    var blurred = imeHardReset(cm);
    setTimeout(function () {
      try {
        if (!cm) return;
        var cur = cm.getCursor();
        cm.focus();
        if (blurred) {
          cm.setCursor({ line: cur.line, ch: 0 });
          cm.setCursor({ line: cur.line, ch: cur.ch });
        } else {
          cm.setCursor({ line: cur.line, ch: cur.ch });
        }
      } catch (e) { }
    }, blurred ? 90 : 0);
  }

  /* ═══════════════════════════════════════════════════════════════
  INSERTION ENGINE — the ONE place text enters the document
  ═══════════════════════════════════════════════════════════════ */
  function applyItem(item) {
    var cm = cmRef; if (!cm || !item) return;
    var label = item.label;
    var insert = (item.insert != null && typeof item.insert === 'string') ? item.insert : label;
    var cur = cm.getCursor();
    var line = ''; try { line = cm.getLine(cur.line) || ''; } catch (e) { }
    var end = Math.min(cur.ch, line.length);
    var start = end;
    while (start > 0 && /[\w$]/.test(line.charAt(start - 1))) start--;
    var from = start - absorbPrefix(line.slice(0, start), insert);
    if (item.type === 'boilerplate' && popup.meta && popup.meta.docEmpty) from = 0;
    var to = end;

    var isCall = insert.indexOf('(') !== -1 && /[)](\uE002|;)?$/.test(insert);

    /* Paren-aware: "name(" already on screen → insert only the name and
       park the caret inside the existing parens (never double parens,
       and never a statement ";" inside an argument list). */
    if (isCall && line.charAt(to) === '(') {
      insert = label;
      cm.replaceRange(insert, { line: cur.line, ch: from }, { line: cur.line, ch: to }, 'complete');
      cm.setCursor({ line: cur.line, ch: from + label.length + 1 });
      jumpStops = [];
      afterPick(label, false);
      return;
    }

    /* ★ v11 SMART SEMICOLON: the token becomes ";" only when the call
       completes a statement at the end of the line. */
    if (insert.indexOf(SEMI_TOKEN) !== -1) {
      var nextCh = line.charAt(to) || '';
      insert = insert.split(SEMI_TOKEN).join(nextCh === '' ? ';' : '');
    }

    /* Right-side duplicate swallowing (punctuation tails only) */
    var tailMax = Math.min(6, insert.length);
    for (var L = tailMax; L >= 1; L--) {
      var suf = insert.slice(insert.length - L);
      if (/[\w\s]/.test(suf)) continue;
      if (suf.indexOf(CURSOR_MARKER) !== -1 || suf.indexOf(TAB_STOP) !== -1) continue;
      if (line.substr(to, L) === suf) { to += L; break; }
    }

    cm.replaceRange(insert, { line: cur.line, ch: from }, { line: cur.line, ch: to }, 'complete');
    placeMarkers(cm, cur.line, from, insert);

    /* ★ v11: a completed function call selects its first placeholder so
       you can just start typing the real argument. */
    var hadParams = item.type === 'function' && extractParams(item.detail);
    afterPick(label, !!hadParams);
  }

  function placeMarkers(cm, startLine, startCh, insert) {
    var re = new RegExp('\uE000|\uE001', 'g');
    var m;
    var order = [];
    while ((m = re.exec(insert)) !== null) {
      var before = insert.slice(0, m.index).split('\n');
      var ln = startLine + before.length - 1;
      var ch = before.length > 1 ? before[before.length - 1].length : startCh + before[0].length;
      order.push({ kind: m[0] === CURSOR_MARKER ? 'caret' : 'stop', line: ln, ch: ch });
    }
    for (var i = order.length - 1; i >= 0; i--) {
      cm.replaceRange('', { line: order[i].line, ch: order[i].ch }, { line: order[i].line, ch: order[i].ch + 1 });
    }
    var caretPos = null, stops = [];
    for (var j = 0; j < order.length; j++) {
      if (order[j].kind === 'caret' && !caretPos) caretPos = order[j];
      else stops.push(order[j]);
    }
    if (caretPos) cm.setCursor({ line: caretPos.line, ch: caretPos.ch });
    else if (stops.length) {
      cm.setCursor({ line: stops[0].line, ch: stops[0].ch });
      stops = stops.slice(1);
    }
    jumpStops = stops;
  }

  function jumpNextStop() {
    var cm = cmRef; if (!cm || !jumpStops.length) return false;
    var s = jumpStops.shift();
    try {
      cm.setCursor({ line: s.line, ch: s.ch });
      selectWordAt(cm, s.line, s.ch);   // ★ v11: select the next placeholder
      cm.focus();
    } catch (e) { }
    return true;
  }

  function pickIsNoop(cm, item) {
    try {
      var cur = cm.getCursor();
      var line = cm.getLine(cur.line) || '';
      var end = Math.min(cur.ch, line.length);
      var start = end;
      while (start > 0 && /[\w$]/.test(line.charAt(start - 1))) start--;
      var word = line.slice(start, end);
      if (!word) return false;
      var label = String(item.label == null ? '' : item.label);
      if (label !== word) return false;
      return item.insert == null || item.insert === label;
    } catch (e) { return false; }
  }

  function afterPick(label, selectPlaceholder) {
    try { bumpRecent(popup.meta ? popup.meta.lang : (liveFileLangId(cmRef) || ''), label); } catch (e) { }
    if (selectPlaceholder && cmRef) {
      selectWordAtCaret(cmRef);
      /* the selection IS the IME resync — a normal settle would collapse it */
      return;
    }
    settleCaret(cmRef);
  }

  /* ═══════════════════════════════════════════════════════════════
  POPUP UI
  ═══════════════════════════════════════════════════════════════ */
  var INTEL_GLYPHS = { keyword: '◆', function: 'ƒ', snippet: '⧉', boilerplate: '🏗', property: '▪', variable: '𝑥', module: '▤', text: '·' };
  var popup = { el: null, listEl: null, items: [], active: 0, open: false, meta: null };
  var touchStartY = 0;

  function ensurePopup() {
    if (popup.el) return popup.el;
    var el = document.createElement('div');
    el.id = 'm-ac-popup';
    el.className = 'CodeMirror-hints m-ac-popup';
    var list = document.createElement('div');
    list.className = 'm-ac-list';
    el.appendChild(list);
    el.addEventListener('mousedown', function (e) { e.preventDefault(); });
    el.addEventListener('touchstart', function (e) {
      touchStartY = e.touches[0] ? e.touches[0].clientY : 0;
    }, { passive: true });
    el.addEventListener('touchend', function (e) {
      var y = e.changedTouches[0] ? e.changedTouches[0].clientY : 0;
      if (Math.abs(y - touchStartY) > 10) return;
      var it = e.target.closest ? e.target.closest('[data-ac-idx]') : null;
      if (it) {
        e.preventDefault();
        pickIndex(parseInt(it.getAttribute('data-ac-idx'), 10));
      }
    }, { passive: false });
    el.addEventListener('click', function (e) {
      var it = e.target.closest ? e.target.closest('[data-ac-idx]') : null;
      if (it) pickIndex(parseInt(it.getAttribute('data-ac-idx'), 10));
    });
    document.body.appendChild(el);
    popup.el = el;
    popup.listEl = list;
    return el;
  }

  function renderPopup() {
    var html = '';
    var lastType = null;
    var hasBoth = popup.items.some(function (x) { return x.type === 'boilerplate'; }) &&
      popup.items.some(function (y) { return y.type === 'snippet'; });
    for (var i = 0; i < popup.items.length; i++) {
      var it = popup.items[i];
      var type = it.type || 'text';
      if (hasBoth && (type === 'boilerplate' || type === 'snippet') && lastType !== type) {
        html += '<div class="m-ac-divider">' +
          (type === 'boilerplate'
            ? (popup.meta && popup.meta.docEmpty ? '🏗 Starters — pick one to scaffold the file' : '🏗 Boilerplates')
            : '⧉ Snippets') +
          '</div>';
      }
      lastType = type;
      html += '<div class="CodeMirror-hint m-hint-item' + (i === popup.active ? ' CodeMirror-hint-active' : '') + '" data-ac-idx="' + i + '">' +
        '<span class="m-hint-glyph m-hint-glyph-' + type + '">' + (INTEL_GLYPHS[type] || '·') + '</span>' +
        '<span class="m-hint-label">' + esc(it.label) + '</span>' +
        (it.detail ? '<span class="m-hint-detail">' + esc(it.detail) + '</span>' : '') +
        '</div>';
    }
    popup.listEl.innerHTML = html;
    var act = popup.listEl.children[popup.active];
    if (act && act.scrollIntoView) {
      try { act.scrollIntoView({ block: 'nearest' }); } catch (e) { }
    }
  }

  function positionPopup() {
    var el = popup.el; var cm = cmRef;
    if (!el || !cm) return;
    var c = cursorClientCoords(cm);
    if (!c) return;
    var vw = window.innerWidth;
    var vhBottom = window.innerHeight;
    try {
      if (window.visualViewport) {
        vhBottom = window.visualViewport.offsetTop + window.visualViewport.height;
      }
    } catch (e) { }
    var w = el.offsetWidth || 260;
    var h = el.offsetHeight || 200;
    var left = Math.max(8, Math.min(c.left - 12, vw - w - 8));
    var top = c.bottom + 6;
    if (top + h > vhBottom - 8) top = Math.max(8, c.top - h - 6);
    el.style.position = 'fixed';
    el.style.left = left + 'px';
    el.style.top = top + 'px';
  }

  function openPopup(res) {
    if (!res || !res.list || !res.list.length) { closePopup(); return; }
    var prevLabel = (popup.open && popup.items[popup.active]) ? popup.items[popup.active].label : null;
    popup.items = res.list;
    popup.meta = res;
    popup.active = 0;
    if (prevLabel) {
      for (var i = 0; i < res.list.length; i++) {
        if (res.list[i].label === prevLabel) { popup.active = i; break; }
      }
    }
    popup.open = true;
    var el = ensurePopup();
    el.style.display = 'block';
    renderPopup();
    positionPopup();
  }
  function closePopup() {
    if (!popup.open && !popup.el) return;
    popup.open = false;
    popup.items = [];
    popup.meta = null;
    if (popup.el) popup.el.style.display = 'none';
  }
  function moveActive(delta) {
    if (!popup.open || !popup.items.length) return;
    popup.active = (popup.active + delta + popup.items.length) % popup.items.length;
    renderPopup();
  }
  function pickIndex(i) {
    if (!popup.open || !popup.items[i]) return;
    var item = popup.items[i];
    closePopup();
    applyItem(item);
  }
  function refreshIfOpen() {
    if (!popup.open) return;
    var cm = cmRef; if (!cm) { closePopup(); return; }
    var cur; try { cur = cm.getCursor(); } catch (e) { closePopup(); return; }
    if (popup.meta && cur.line !== popup.meta.line) { closePopup(); return; }
    var res = computeCompletions(cm, false);
    if (!res) { closePopup(); return; }
    openPopup(res);
  }

  /* ═══════════════════════════════════════════════════════════════
  TRIGGERING — four-signal auto-popup (IME-proof)
  ═══════════════════════════════════════════════════════════════ */
  function trackDocLength(cm) {
    if (typeof cm._qkAcLen !== 'number') {
      try { cm._qkAcLen = cm.getValue().length; } catch (e) { cm._qkAcLen = 0; }
    }
    cm.on('change', function (c2, co) {
      try {
        while (co) {
          var added = co.text ? co.text.join('\n').length : 0;
          var removed = co.removed ? co.removed.join('\n').length : 0;
          c2._qkAcLen = Math.max(0, (c2._qkAcLen || 0) + added - removed);
          co = co.next;
        }
      } catch (e) { }
    });
  }
  function perfModeLikely(cm) {
    try {
      if (cm.lineCount() > PERF_LINES) return true;
      var len = (typeof cm._qkAcLen === 'number') ? cm._qkAcLen : 0;
      return len > PERF_CHARS;
    } catch (e) { return false; }
  }
  function autoAllowed(cm) {
    if (!featureOn || !enabled) return false;
    if (typeof autoPopup === 'boolean' && !autoPopup) return false;
    if (!cm) return false;
    try { if (window.QUIRKY_DEVICE_TIER === 'low') return false; } catch (e) { }
    if (perfModeLikely(cm)) return false;
    try { if (inCommentOrString(cm, cm.getCursor())) return false; } catch (e) { }
    return true;
  }
  function scheduleOpen(cm) {
    clearTimeout(debounceTimer);
    var wait = composing ? COMPOSING_DEBOUNCE_MS : AUTO_DEBOUNCE_MS;
    debounceTimer = setTimeout(function () {
      debounceTimer = null;
      if (cmRef !== cm) return;
      if (popup.open) { refreshIfOpen(); return; }
      if (!autoAllowed(cm)) return;
      maybeOpen(false);
    }, wait);
  }
  function boilerplateTrigger(cm) {
    if (!lastChangeText) return null;
    var text = '';
    try { text = cm.getValue(); } catch (e) { return null; }
    if (/[^\s]/.test(text.split(lastChangeText).join(''))) return null;
    var eff = resolveCursorLang(cm);
    var bps = boilerplatesFor(eff);
    var hits = [], i;
    for (i = 0; i < bps.length; i++) {
      if (bps[i].trigger && lastChangeText === bps[i].trigger) hits.push(bps[i]);
    }
    if (!hits.length && lastChangeText === '#') {
      for (i = 0; i < bps.length; i++) {
        if (/strict|script|header|plain/i.test(bps[i].label)) hits.push(bps[i]);
      }
    }
    if (!hits.length) return null;
    var cur;
    try { cur = cm.getCursor(); } catch (e2) { return null; }
    var lineText = '';
    try { lineText = cm.getLine(cur.line) || ''; } catch (e3) { }
    var list = [];
    for (var k = 0; k < hits.length; k++) {
      list.push({
        label: hits[k].label,
        insert: expandBody(hits[k].body, cm, lineText.slice(0, cur.ch)),
        detail: hits[k].detail || 'boilerplate',
        type: 'boilerplate'
      });
    }
    return {
      list: list, word: lastChangeText, lang: eff, line: cur.line,
      start: Math.max(0, cur.ch - 1), end: cur.ch, docEmpty: true
    };
  }
  function maybeOpenBoilerplates() {
    var cm = cmRef; if (!cm) return false;
    var cur; try { cur = cm.getCursor(); } catch (e) { return false; }
    var lineText = ''; try { lineText = cm.getLine(cur.line) || ''; } catch (e2) { }
    var end = Math.min(cur.ch, lineText.length), start = end;
    while (start > 0 && /[\w$]/.test(lineText.charAt(start - 1))) start--;
    var word = lineText.slice(start, end);
    var eff = resolveCursorLang(cm);
    var bps = boilerplatesFor(eff);
    if (!bps.length) {
      if (U && U.toast) U.toast('No boilerplates for this language yet', 'info');
      return false;
    }
    var list = [];
    for (var i = 0; i < bps.length; i++) {
      var sc = word ? matchScore(bps[i].label, word) : 1;
      if (word && sc < 0) continue;
      list.push({
        label: bps[i].label,
        insert: expandBody(bps[i].body, cm, lineText.slice(0, cur.ch)),
        detail: bps[i].detail || 'boilerplate',
        type: 'boilerplate'
      });
    }
    if (!list.length) {
      if (U && U.toast) U.toast('No boilerplate matches "' + word + '"', 'info');
      return false;
    }
    var fullDoc = ''; try { fullDoc = cm.getValue(); } catch (e3) { }
    openPopup({
      list: list, word: word, lang: eff, line: cur.line,
      start: start, end: end, docEmpty: !/[^\s]/.test(fullDoc)
    });
    return true;
  }
  function tagTrigger(cm) {
    if (lastChangeText !== '<') return null;
    var eff = resolveCursorLang(cm);
    if (eff !== 'html' && eff !== 'php' && eff !== 'php+html') return null;
    var res = computeCompletions(cm, true);
    if (!res) return null;
    res.list = res.list.filter(function (it) {
      return it.type === 'snippet' && typeof it.insert === 'string' && it.insert.charAt(0) === '<';
    });
    return res.list.length ? res : null;
  }
  function maybeOpen(manual) {
    var cm = cmRef; if (!cm) return;
    if (!manual && !autoAllowed(cm)) return;
    var res = computeCompletions(cm, manual);
    if (!manual && !res) res = tagTrigger(cm) || boilerplateTrigger(cm);
    if (!res || !res.list || !res.list.length) {
      if (!manual) closePopup();
      return;
    }
    openPopup(res);
  }
  function onInputRead(cm, obj) {
    try {
      var txt = (obj && obj.text) ? obj.text.join('') : '';
      lastChangeText = txt;
      if (popup.open) { refreshIfOpen(); return; }
      if (!autoAllowed(cm)) return;
      if (txt.length < 1 || txt.length > 4) return;
      if (!/[\w$<]/.test(txt.charAt(txt.length - 1))) return;
      scheduleOpen(cm);
    } catch (e) { }
  }
  function onChangeMaybeType(cm, co) {
    try {
      if (!co) return;
      var o = String(co.origin || '');
      if (o.indexOf('undo') !== -1 || o.indexOf('redo') !== -1 ||
        o.indexOf('cut') !== -1 || o.indexOf('delete') !== -1 || o === 'paste') {
        if (popup.open) refreshIfOpen();
        return;
      }
      if (!co.text || co.text.length === 0) return;
      var joined = co.text.join('\n');
      lastChangeText = joined;
      if (popup.open && joined === '(') {
        var act = popup.items[popup.active];
        if (act && act.type === 'function') { pickIndex(popup.active); return; }
      }
      if (popup.open) { refreshIfOpen(); return; }
      if (!autoAllowed(cm)) return;
      if (joined.length < 1 || joined.length > 8) return;
      if (!/[\w$<]/.test(joined.charAt(joined.length - 1))) return;
      scheduleOpen(cm);
    } catch (e) { }
  }

  /* ═══════════════════════════════════════════════════════════════
  KEYBOARD CONTROL
  ═══════════════════════════════════════════════════════════════ */
  function onEditorKeydown(e) {
    if (e.isComposing || composing) return;
    if (popup.open) {
      if (e.key === 'ArrowDown') { e.preventDefault(); moveActive(1); return; }
      if (e.key === 'ArrowUp') { e.preventDefault(); moveActive(-1); return; }
      if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); pickIndex(popup.active); return; }
      if (e.key === 'Escape') { e.preventDefault(); closePopup(); return; }
      if (e.key === '(') {
        var it = popup.items[popup.active];
        if (it && it.type === 'function') { e.preventDefault(); pickIndex(popup.active); return; }
      }
    } else {
      if (e.key === 'Tab' && jumpStops.length) { e.preventDefault(); jumpNextStop(); return; }
      if (e.key === 'Escape' && jumpStops.length) { jumpStops = []; }
    }
  }

  /* ═══════════════════════════════════════════════════════════════
  ATTACH + LIFECYCLE
  ═══════════════════════════════════════════════════════════════ */
  function activeCM() {
    try {
      if (window.IDE.mobileEditor && typeof window.IDE.mobileEditor.getCM === 'function') {
        return window.IDE.mobileEditor.getCM();
      }
    } catch (e) { }
    return null;
  }
  function applyProvider(cm) {
    try {
      if (cm && typeof cm.setCompletionProvider === 'function') cm.setCompletionProvider(null);
    } catch (e) { }
  }
  function attach(cm) {
    if (!cm || cm._qkAcWired) return;
    cm._qkAcWired = true;
    try {
      trackDocLength(cm);
      cm.on('inputRead', onInputRead);
      cm.on('change', onChangeMaybeType);
      var wrap = wrapperOf(cm);
      if (wrap) {
        wrap.addEventListener('compositionstart', function () { composing = true; }, true);
        wrap.addEventListener('compositionend', function () {
          composing = false;
          setTimeout(function () {
            if (cmRef !== cm) return;
            if (popup.open) { refreshIfOpen(); return; }
            if (autoAllowed(cm)) maybeOpen(false);
          }, 80);
        }, true);
        wrap.addEventListener('keydown', function (e) {
          if (e.key && e.key.length === 1 && !e.ctrlKey && !e.metaKey) scheduleOpen(cm);
        }, true);
        wrap.addEventListener('keydown', onEditorKeydown, true);
      }
      var scroller = scrollerOf(cm);
      if (scroller) {
        scroller.addEventListener('scroll', function () { closePopup(); }, { passive: true });
      }
      try {
        cm.on('cursorActivity', function () {
          if (popup.open) {
            var cur; try { cur = cm.getCursor(); } catch (e) { cur = null; }
            if (!cur || (popup.meta && cur.line !== popup.meta.line)) closePopup();
            else positionPopup();
          }
        });
      } catch (e) { }

      var prevExtra = null;
      try { prevExtra = cm.getOption('extraKeys'); } catch (e) { prevExtra = null; }
      var merged = {};
      var k;
      if (prevExtra) {
        for (k in prevExtra) {
          if (Object.prototype.hasOwnProperty.call(prevExtra, k)) merged[k] = prevExtra[k];
        }
      }
      var prevTab = merged.Tab;
      merged.Tab = function (c2) {
        if (popup.open) {
          var act = popup.items[popup.active];
          if (act && !pickIsNoop(c2, act)) { pickIndex(popup.active); return; }
          closePopup();
        }
        if (jumpStops.length) { jumpNextStop(); settleCaret(c2); return; }
        var doIndent = function () {
          if (typeof prevTab === 'function') { prevTab(c2); }
          else { try { c2.execCommand('insertTab'); } catch (e) { } }
          settleCaret(c2);
        };
        if (composing) { imeCommit(c2); setTimeout(doIndent, 80); return; }
        doIndent();
      };
      try { cm.setOption('extraKeys', merged); } catch (e) { }
      applyProvider(cm);
    } catch (e) { }
  }

  function syncFromTab(detail) {
    var d = detail || {};
    fileLangId = d.id || null;
    if (!fileLangId && d.ext && window.IDE.language) {
      try { fileLangId = window.IDE.language.byExt(d.ext).id; } catch (e) { fileLangId = null; }
    }
    clearTimeout(debounceTimer);
    docWordCache = { key: null, words: [] };
    docSymbolCache = { key: null, symbols: [] };
    jumpStops = [];
    closePopup();
    cmRef = activeCM();
    if (cmRef) {
      attach(cmRef);
      applyProvider(cmRef);
    }
  }

  function wireBus() {
    if (!U || typeof U.on !== 'function') return;
    U.on('editor:tab-shown', function (e) { syncFromTab(e && e.detail); });
    U.on('file:open', function () {
      var cm = activeCM();
      if (cm) { attach(cm); applyProvider(cm); }
    });
    /* ★ v11 LIVE RENAME SYNC — the editor tab is already updated by
       mobile-editor.js (it registers first); we re-derive the language
       straight from the NEW path so pools/snippets switch instantly. */
    U.on('file:renamed', function (e) {
      var d = (e && e.detail) || {};
      if (!d.to) return;
      var ext = String(d.to).split('.').pop().toLowerCase();
      var id = null;
      if (window.IDE.language) {
        try { id = window.IDE.language.byExt(ext).id; } catch (err) { id = null; }
      }
      clearTimeout(debounceTimer);
      docWordCache = { key: null, words: [] };
      docSymbolCache = { key: null, symbols: [] };
      jumpStops = [];
      closePopup();
      fileLangId = id;
      cmRef = activeCM();
      if (cmRef) attach(cmRef);
    });
  }

  /* ═══════════════════════════════════════════════════════════════
  PUBLIC API
  ═══════════════════════════════════════════════════════════════ */
  window.IDE.autocomplete = {
    trigger: function () {
      var cm = cmRef || activeCM();
      if (!cm || !featureOn) return false;
      cmRef = cm;
      attach(cm);
      clearTimeout(debounceTimer);
      maybeOpen(true);
      return popup.open;
    },
    triggerBoilerplates: function () {
      var cm = cmRef || activeCM();
      if (!cm || !featureOn) return false;
      cmRef = cm;
      attach(cm);
      clearTimeout(debounceTimer);
      return maybeOpenBoilerplates();
    },
    close: closePopup,
    isEnabled: function () { return featureOn && enabled; },
    setEnabled: function (b) {
      enabled = !!b;
      applyProvider(cmRef || activeCM());
      if (!enabled) closePopup();
    },
    isAutoPopup: function () { return autoPopup; },
    setAutoPopup: function (b) {
      autoPopup = !!b;
      try { localStorage.setItem(LS_AUTO_POPUP, autoPopup ? '1' : '0'); } catch (e) { }
      if (!autoPopup) closePopup();
      try { if (U && U.emit) U.emit('autocomplete:autopopup-changed', { on: autoPopup }); } catch (e) { }
      return autoPopup;
    },
    registerPool: registerPool,
    installPool: installPool,
    popupOpen: function () { return popup.open; },
    pick: function () { if (popup.open) pickIndex(popup.active); },
    move: moveActive,
    jumpStop: jumpNextStop
  };

  function boot() {
    loadStoredPools();
    wireBus();
    document.addEventListener('touchstart', function (e) {
      if (!popup.open) return;
      if (popup.el && e.target && popup.el.contains(e.target)) return;
      closePopup();
    }, { passive: true });
    var _snipPollCount = 0;
    var _snipPoll = setInterval(function () {
      _snipPollCount++;
      var MS = window.IDE && window.IDE.mobileSnippets;
      if (MS && typeof MS.refreshAutocomplete === 'function') {
        MS.refreshAutocomplete();
        clearInterval(_snipPoll);
      } else if (_snipPollCount > 20) {
        clearInterval(_snipPoll);
      }
    }, 200);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();