import category from "category.es6";
import attributes from "column/config/attributes.es6";
import supports from "column/config/supports.es6";
import getEditWrapperProps from "column/config/getEditWrapperProps.es6";
import BlockEdit from "column/components/BlockEdit.es6";
import BlockSave from "column/components/BlockSave.es6";

const { registerBlockType } = wp.blocks;

registerBlockType(category.slug + "/column", {
  title: Drupal.t("Column"),
  description: Drupal.t("A single Bootstrap column within a row block."),
  icon: "columns",
  category: category.slug,
  parent: ["bootstrap/row"],
  attributes,
  supports,
  getEditWrapperProps,
  edit: BlockEdit,
  save: BlockSave
});
