import withInnerBlocks from "withInnerBlocks.es6";
import BlockVariationPicker from "common/BlockVariationPicker.es6";
import BlockToolbar from "row/components/BlockToolbar.es6";

const { compose } = wp.compose;
// Fallback to 'wp.editor' for backwards compatibility
const { InnerBlocks } = wp.blockEditor || wp.editor;
const { Fragment } = wp.element;

const ALLOWED_BLOCKS = ["bootstrap/column"];

const BlockEdit = ({
  clientId,
  name,
  className,
  attributes,
  isSelected,
  hasInnerBlocks,
  setAttributes
}) => {
  if (hasInnerBlocks) {
    return (
      <Fragment>
        <InnerBlocks
          allowedBlocks={ALLOWED_BLOCKS}
          renderAppender={
            (!hasInnerBlocks || isSelected) && InnerBlocks.ButtonBlockAppender
          }
          __experimentalPassedProps={{
            className
          }}
        />
        <BlockToolbar attributes={attributes} setAttributes={setAttributes} />
      </Fragment>
    );
  }
  return (
    <BlockVariationPicker
      clientId={clientId}
      name={name}
      setAttributes={setAttributes}
    />
  );
};

export default compose(withInnerBlocks)(BlockEdit);
