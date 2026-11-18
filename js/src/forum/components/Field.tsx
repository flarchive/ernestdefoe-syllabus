import type Mithril from 'mithril';

/** A labelled form group, the way Flarum's own forms lay them out. */
export default function Field(label: Mithril.Children, control: Mithril.Children, help?: Mithril.Children, id?: string): Mithril.Children {
  return (
    <div className="Form-group">
      <label for={id}>{label}</label>
      {control}
      {help ? <div className="helpText">{help}</div> : null}
    </div>
  );
}
