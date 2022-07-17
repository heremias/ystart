import cx from "classnames";

// Fallback to 'wp.editor' for backwards compatibility
const { InnerBlocks } = wp.blockEditor || wp.editor;

const BlockSave = ({ className, attributes }) => {
  const { horizontalAlignment, verticalAlignment } = attributes;
  const rowClassName = cx(
    "row",
    {
      "justify-content-start":
        horizontalAlignment && horizontalAlignment === "left",
      "justify-content-center":
        horizontalAlignment && horizontalAlignment === "center",
      "justify-content-end":
        horizontalAlignment && horizontalAlignment === "right",
      "align-items-start": verticalAlignment && verticalAlignment === "top",
      "align-items-center": verticalAlignment && verticalAlignment === "center",
      "align-items-end": verticalAlignment && verticalAlignment === "bottom"
    },
    className
  );

  return (
    <div className={rowClassName}>
      <InnerBlocks.Content />
    </div>
  );
};

export default BlockSave;
