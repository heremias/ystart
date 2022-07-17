const { BlockControls, BlockAlignmentToolbar, BlockVerticalAlignmentToolbar } =
  wp.blockEditor || wp.editor;

const BlockToolbar = ({ attributes, setAttributes }) => {
  const { horizontalAlignment, verticalAlignment } = attributes;

  return (
    <BlockControls>
      <BlockAlignmentToolbar
        controls={["left", "center", "right"]}
        value={horizontalAlignment}
        onChange={newAlignment =>
          setAttributes({ horizontalAlignment: newAlignment })
        }
      />
      <BlockVerticalAlignmentToolbar
        value={verticalAlignment}
        onChange={newAlignment =>
          setAttributes({ verticalAlignment: newAlignment })
        }
      />
    </BlockControls>
  );
};

export default BlockToolbar;
