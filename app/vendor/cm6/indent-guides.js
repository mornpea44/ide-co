/**
 * QUIRKY IDE — CM6 INDENT GUIDES (vendored, offline)
 * ═══════════════════════════════════════════════════════════════
 * A tiny self-contained indent-guide extension. CodeMirror 6 has no
 * built-in guides and community ones pull extra deps, so this keeps to
 * the two packages the app already vendors (@codemirror/view +
 * @codemirror/state, resolved through the shell's import map).
 *
 * HOW IT DRAWS: for every visible line, each leading SPACE character
 * sitting at an indent-unit boundary (column % tabSize === 0, column
 * > 0) gets a MARK decoration carrying .cm-ig-unit. CSS paints a 1px
 * vertical rule via ::before on the marked span. Mark decorations
 * never shift layout — text width is untouched.
 *
 * Tabs are skipped deliberately: a rule over a tab glyph spans its
 * whole width and looks broken. Mixed-indent files simply show fewer
 * guides — acceptable for a hint feature.
 *
 * EXPORTS: indentGuides() → Extension
 */
import { ViewPlugin, Decoration } from '@codemirror/view';
import { EditorState } from '@codemirror/state';

function buildDecos(view) {
  var decos = [];
  var unit = Math.max(1, view.state.facet(EditorState.tabSize));
  var from = view.viewport.from;
  var to = view.viewport.to;
  var startNum = view.state.doc.lineAt(from).number;
  var endNum = view.state.doc.lineAt(to).number;

  for (var n = startNum; n <= endNum; n++) {
    var line = view.state.doc.line(n);
    var text = line.text;
    var wsEnd = 0;
    while (wsEnd < text.length && (text[wsEnd] === ' ' || text[wsEnd] === '\t')) wsEnd++;
    var col = 0;
    for (var i = 0; i < wsEnd; i++) {
      if (text[i] === ' ' && i > 0 && col % unit === 0) {
        decos.push(Decoration.mark({ class: 'cm-ig-unit' }).range(line.from + i));
      }
      col += (text[i] === '\t') ? (unit - (col % unit)) : 1;
    }
  }
  return Decoration.set(decos, true); // set() sorts when second arg is true
}

class IgPlugin {
  constructor(view) { this.decos = buildDecos(view); }
  update(u) {
    if (u.docChanged || u.viewportChanged) this.decos = buildDecos(u.view);
  }
}

var igPlugin = ViewPlugin.fromClass(IgPlugin, {
  decorations: function (v) { return v.decos; },
});

export function indentGuides() {
  return [igPlugin];
}
