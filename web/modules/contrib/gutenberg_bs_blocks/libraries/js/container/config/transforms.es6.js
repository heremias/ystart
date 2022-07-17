import category from "category.es6";

const { createBlock } = wp.blocks;

export default {
  from: [
    {
      type: "block",
      blocks: ["core/group"],
      transform: (attributes, innerBlocks) => {
        return createBlock(
          category.slug + "/container",
          attributes,
          innerBlocks
        );
      }
    },
    {
      type: "block",
      isMultiBlock: true,
      blocks: ["*"],
      __experimentalConvert(blocks) {
        // Avoid transforming a single `core/group` Block
        if (
          blocks.length === 1 &&
          (blocks[0].name === "core/group" ||
            blocks[0].name === category.slug + "/container")
        ) {
          return;
        }

        const alignments = ["wide", "full"];

        // Determine the widest setting of all the blocks to be grouped
        const widestAlignment = blocks.reduce((accumulator, block) => {
          const { align } = block.attributes;
          return alignments.indexOf(align) > alignments.indexOf(accumulator)
            ? align
            : accumulator;
        }, undefined);

        // Clone the Blocks to be Grouped
        // Failing to create new block references causes the original blocks
        // to be replaced in the switchToBlockType call thereby meaning they
        // are removed both from their original location and within the
        // new group block.
        const groupInnerBlocks = blocks.map(block => {
          return createBlock(block.name, block.attributes, block.innerBlocks);
        });

        return createBlock(
          category.slug + "/container",
          {
            align: widestAlignment
          },
          groupInnerBlocks
        );
      }
    }
  ]
};
