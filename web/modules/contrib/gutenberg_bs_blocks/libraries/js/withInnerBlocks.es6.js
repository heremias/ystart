const { withSelect } = wp.data;

const withInnerBlocks = withSelect((select, ownProps) => {
  const { clientId } = ownProps;
  // Fallback to 'core/editor' for backwards compatibility
  const { getBlockOrder } =
    select("core/block-editor") || select("core/editor");

  return {
    hasInnerBlocks: getBlockOrder(clientId).length > 0
  };
});

export default withInnerBlocks;
