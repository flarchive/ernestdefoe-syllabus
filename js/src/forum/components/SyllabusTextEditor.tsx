import TextEditor from 'flarum/common/components/TextEditor';

/**
 * The forum's own text editor, so course descriptions and lessons are written
 * in exactly the format posts are: Markdown, BBCode, FoF Rich Text or Scribe.
 * Its submit button, preview and rich-text switch belong to the composer and
 * are left out; the page has its own Save.
 */
export default class SyllabusTextEditor extends TextEditor {
  controlItems() {
    const items = super.controlItems();

    ['submit', 'preview', 'rich-text'].forEach((key) => items.has(key) && items.remove(key));

    return items;
  }
}
