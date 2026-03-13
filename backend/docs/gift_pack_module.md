# Gift Pack Module

## 模块定位

- 礼包本体是 item，正式 carrier 为 `items.item_id`
- 礼包内容统一走 `gift_pack_items.item_id`
- 不再为货币 / 材料 / 装备拆多套礼包结构
- V1 不允许礼包套礼包

## 表关系

- `gift_packs.item_id` -> `items.item_id`
  - 对应 item 必须是 `main_type = gift_pack`
- `gift_pack_items.pack_id` -> `gift_packs.pack_id`
- `gift_pack_items.item_id` -> `items.item_id`
  - V1 不允许再指向 `gift_pack` 类型 item

## 一、礼包主表 `gift_packs`

字段：

- `pack_id`
- `item_id`
- `pack_name`
- `display_name`
- `pack_type`
- `pack_mode`
- `open_mode`
- `select_count_min`
- `select_count_max`
- `desc`
- `icon`
- `is_enabled`
- `sort_order`
- `remark`

### pack_type

- `stage_reward_pack`
- `growth_pack`
- `shop_pack`
- `milestone_pack`

### pack_mode

- `fixed`
- `select_one`
- `select_multi`

### open_mode

- `manual`
- `auto_grant`

### 规则

- `item_id` 必须命中已启用的礼包 item
- `fixed`
  - 只允许固定内容
  - `select_count_min / max` 为空
- `select_one`
  - 必须固定为 `1 / 1`
  - 必须至少有 1 条启用的 `selectable` 内容
- `select_multi`
  - 必须至少有 1 条启用的 `selectable` 内容
  - `select_count_max` 不能超过启用的自选条目数

## 二、礼包内容表 `gift_pack_items`

字段：

- `pack_id`
- `item_id`
- `content_mode`
- `count_min`
- `count_max`
- `weight`
- `recommended_sect`
- `display_note`
- `sort_order`
- `is_enabled`
- `remark`

### content_mode

- `fixed`
- `selectable`

### recommended_sect

- `none`
- `bing`
- `jingang`
- `fulu`

### 规则

- `count_min / count_max` 表示发放数量区间
- `recommended_sect` 仅做推荐展示，不做宗门限制
- `weight` 字段保留，但 V1 不重点启用随机逻辑

## 三、正式支持的礼包形式

### 纯固定礼包

- 所有内容都在 `fixed` 条目中维护

### 纯自选礼包

- 所有内容都在 `selectable` 条目中维护
- `select_one` 或 `select_multi` 决定选择数量

### 固定 + 自选混合礼包

- 允许一个礼包同时存在 `fixed` 和 `selectable`
- 固定内容直接发放
- 自选内容按 `select_count_min / max` 供前端选择

## 四、后台维护口径

- `基础信息`：维护礼包主表字段
- `固定内容`：只维护 `content_mode = fixed`
- `自选内容`：只维护 `content_mode = selectable`
- 推荐宗门只做展示，不做领取限制

## 五、客户端读取建议

- 先读取礼包本体 item
- 再按 `pack_id` 读取 `gift_packs`
- 固定内容直接展示 / 发放
- 自选内容按 `recommended_sect` 做推荐 UI 标签
