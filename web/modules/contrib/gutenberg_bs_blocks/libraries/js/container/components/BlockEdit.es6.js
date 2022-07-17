import withInnerBlocks from "withInnerBlocks.es6";

const { compose } = wp.compose;
// Fallback to 'wp.editor' for backwards compatibility
const { InnerBlocks } = wp.blockEditor || wp.editor;

const BlockEdit = ({ className, hasInnerBlocks }) => {
  return (
    <InnerBlocks
      renderAppender={!hasInnerBlocks && InnerBlocks.ButtonBlockAppender}
      __experimentalPassedProps={{
        className
      }}
    />
  );
};

export default compose(withInnerBlocks)(BlockEdit);
