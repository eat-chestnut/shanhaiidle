# Shop Module

## 模块定位

- 商城模块只负责商品定义、展示分类、发放内容、价格、解锁等级、限购、推荐与上下架
- 不负责支付渠道
- 不负责活动开始 / 结束时间
- 不负责充值逻辑
- 不负责礼包内容定义
- 不负责背包结算细节

## 数据文件

- 文件名：`shop_goods_v1.json`
- 顶层 key：`shop_goods`
- 导出命令：`php artisan game:export-shop-goods`

## 一、主表 `shop_goods`

字段：

- `goods_id`
- `title`
- `display_name`
- `shop_tab`
- `goods_type`
- `reward_item_id`
- `reward_count`
- `price_item_id`
- `price_amount`
- `original_price_amount`
- `unlock_level`
- `buy_limit_type`
- `buy_limit_value`
- `is_recommended`
- `is_enabled`
- `sort_order`
- `desc`
- `icon`
- `remark`

## 二、核心规则

- 一个商品只卖一个 `reward_item_id`
- `reward_item_id` 必须引用 `items.item_id`
- `price_item_id` 必须引用 `items.item_id`
- `price_item_id` 必须是货币类 item
- 商城价格统一使用 `price_item_id + price_amount`
- 商城表不直接挂多条奖励明细
- 多奖励商品必须先做成礼包 item，再由 `reward_item_id` 指向该礼包 item
- 商城首版不做限时活动商品逻辑，不存在 `starts_at / ends_at`

## 三、shop_tab 枚举

- `daily`
  - 日常补给
- `growth`
  - 成长商品
- `sect`
  - 宗门 / 贡献商品
- `special`
  - 特殊推荐页签
  - 仅做展示预留，不做活动时间逻辑

## 四、goods_type 枚举

- `direct_item`
  - 直接卖单个 item
- `gift_pack`
  - 卖礼包 item
  - 礼包内容由 `gift_packs / gift_pack_items` 维护，不在商城表内展开

## 五、buy_limit_type 枚举

- `none`
- `daily`
- `weekly`
- `lifetime`

### 规则

- `none` 时，`buy_limit_value` 可为 `0`
- 其他类型下，`buy_limit_value` 必须大于等于 `1`
- 当前购买链路会根据 `buy_limit_type` 计算剩余次数

## 六、与 items / gift_packs 的关系

- 商城商品本身不是 item
- 商城发放内容统一通过 `shop_goods.reward_item_id` 引用 `items.item_id`
- 商城价格货币统一通过 `shop_goods.price_item_id` 引用 `items.item_id`
- 多奖励场景统一通过礼包 item 承载
- 当 `goods_type = gift_pack` 时，`reward_item_id` 必须是 `main_type = gift_pack` 的 item
- 礼包内容定义仍在礼包模块维护，商城模块不展开礼包内部条目

## 七、首版购买链路说明

- 当前直接接通的价格货币为：
  - `cur_gold`
  - `cur_premium_jade`
  - `cur_contribution`
- 这三类价格货币都先经过 `items.item_id` 建立统一锚点，再映射到玩家当前的钱包字段
