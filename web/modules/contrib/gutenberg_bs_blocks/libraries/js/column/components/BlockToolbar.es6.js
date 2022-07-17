// Fallback to 'wp.editor' for backwards compatibility
const { BlockControls, BlockVerticalAlignmentToolbar } =
  wp.blockEditor || wp.editor;

const BlockToolbar = ({ attributes, setAttributes }) => {
  const { alignment } = attributes;

  return (
    <BlockControls>
      <BlockVerticalAlignmentToolbar
        value={alignment}
        onChange={newAlignment => setAttributes({ alignment: newAlignment })}
      />
    </BlockControls>
  );
};

export default BlockToolbar;
