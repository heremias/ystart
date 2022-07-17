import cx from "classnames";

// Fallback to 'wp.editor' for backwards compatibility
const { InnerBlocks } = wp.blockEditor || wp.editor;

const BlockSave = ({ className, attributes }) => {
  const { align } = attributes;
  const containerClassName = cx(
    {
      container: align === "wide",
      "container-fluid": !align || align === "full"
    },
    className
  );

  return (
    <div className={containerClassName}>
      <InnerBlocks.Content />
    </div>
  );
};

export default BlockSave;
