import withInnerBlocks from "withInnerBlocks.es6";
import BlockToolbar from "column/components/BlockToolbar.es6";
import BlockSidebar from "column/components/BlockSidebar.es6";

const { compose } = wp.compose;
// Fallback to 'wp.editor' for backwards compatibility
const { InnerBlocks } = wp.blockEditor || wp.editor;
const { Fragment } = wp.element;

const BlockEdit = ({
  className,
  hasInnerBlocks,
  attributes,
  setAttributes
}) => {
  return (
    <Fragment>
      <InnerBlocks
        templateLock={false}
        renderAppender={!hasInnerBlocks && InnerBlocks.ButtonBlockAppender}
        __experimentalPassedProps={{
          className
        }}
      />
      <BlockToolbar attributes={attributes} setAttributes={setAttributes} />
      <BlockSidebar attributes={attributes} setAttributes={setAttributes} />
    </Fragment>
  );
};

export default compose(withInnerBlocks)(BlockEdit);
