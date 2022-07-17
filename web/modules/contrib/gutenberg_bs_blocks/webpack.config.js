const path = require("path");
const webpack = require("webpack");
const { CleanWebpackPlugin } = require("clean-webpack-plugin");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const FixStyleOnlyEntriesPlugin = require("webpack-fix-style-only-entries");

const jsPath = path.resolve(__dirname, "libraries/js");
const sassPath = path.resolve(__dirname, "libraries/sass");

module.exports = {
  mode: "production",
  entry: {
    bootstrap: path.resolve(__dirname, sassPath, "bootstrap.scss"),
    "block-edit": path.resolve(__dirname, sassPath, "edit.scss"),
    "block-view": path.resolve(__dirname, sassPath, "view.scss"),

    container: path.resolve(__dirname, jsPath, "container/index.es6.js"),
    "container-edit": path.resolve(__dirname, sassPath, "container/edit.scss"),
    "container-view": path.resolve(__dirname, sassPath, "container/view.scss"),

    row: path.resolve(__dirname, jsPath, "row/index.es6.js"),
    "row-edit": path.resolve(__dirname, sassPath, "row/edit.scss"),

    column: path.resolve(__dirname, jsPath, "column/index.es6.js"),
    "column-edit": path.resolve(__dirname, sassPath, "column/edit.scss")
  },
  output: {
    filename: "js/[name].js",
    path: path.resolve(__dirname, "build")
  },
  module: {
    rules: [
      {
        test: /\.es6\.js$/,
        include: [jsPath],
        exclude: /node_modules/,
        loader: "babel-loader"
      },
      {
        test: /\.scss$/,
        include: [sassPath],
        exclude: /node_modules/,
        use: [
          MiniCssExtractPlugin.loader,
          {
            loader: "css-loader",
            options: {
              sourceMap: false
            }
          },
          "sass-loader"
        ]
      }
    ]
  },
  plugins: [
    new CleanWebpackPlugin(),
    new FixStyleOnlyEntriesPlugin(),
    new MiniCssExtractPlugin({
      filename: "css/[name].css",
      chunkFilename: "css/[id].css"
    })
  ],
  externals: {
    jquery: "jQuery",
    Drupal: "Drupal",
    drupalSettings: "drupalSettings",
    wp: "wp"
  },
  resolve: {
    modules: [jsPath, sassPath, "node_modules"],
    extensions: [".js", ".scss"]
  }
};
