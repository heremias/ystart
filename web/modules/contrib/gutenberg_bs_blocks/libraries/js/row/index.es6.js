import category from "category.es6";
import attributes from "row/config/attributes.es6";
import supports from "row/config/supports.es6";
import getEditWrapperProps from "row/config/getEditWrapperProps.es6";
import variations from "row/config/variations.es6";
import BlockEdit from "row/components/BlockEdit.es6";
import BlockSave from "row/components/BlockSave.es6";

const { registerBlockType } = wp.blocks;

registerBlockType(category.slug + "/row", {
  title: Drupal.t("Row"),
  description: Drupal.t(
    "Add a block that displays content in multiple columns, then add whatever content blocks you'd like."
  ),
  icon: "grid-view",
  category: category.slug,
  attributes,
  supports,
  getEditWrapperProps,
  variations,
  edit: BlockEdit,
  save: BlockSave
});
