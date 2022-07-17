const { useSelect, useDispatch } = wp.data;
const { createBlock } = wp.blocks;
// Fallback to 'wp.editor' for backwards compatibility
const { __experimentalBlockVariationPicker } = wp.blockEditor || wp.editor;

const createBlocksFromInnerBlocksTemplate = innerBlocksTemplate => {
  return innerBlocksTemplate.map(([name, attributes, innerBlocks = []]) =>
    createBlock(
      name,
      attributes,
      createBlocksFromInnerBlocksTemplate(innerBlocks)
    )
  );
};

const BlockVariationPicker = ({ clientId, name, setAttributes }) => {
  const { replaceInnerBlocks } = useDispatch("core/block-editor");
  const { blockType, defaultVariation, variations } = useSelect(
    select => {
      const {
        getBlockVariations,
        getBlockType,
        getDefaultBlockVariation
      } = select("core/blocks");

      return {
        blockType: getBlockType(name),
        defaultVariation: getDefaultBlockVariation(name, "block"),
        variations: getBlockVariations(name, "block")
      };
    },
    [clientId, name]
  );

  return (
    <__experimentalBlockVariationPicker
      label={blockType.title}
      icon={blockType.icon.src}
      variations={variations}
      onSelect={(nextVariation = defaultVariation) => {
        if (nextVariation.attributes) {
          setAttributes(nextVariation.attributes);
        }
        if (nextVariation.innerBlocks) {
          replaceInnerBlocks(
            clientId,
            createBlocksFromInnerBlocksTemplate(nextVariation.innerBlocks)
          );
        }
      }}
      allowSkip
    />
  );
};

export { createBlocksFromInnerBlocksTemplate };

export default BlockVariationPicker;
