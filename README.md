#  mod_quickpractice — Moodle 快速练习插件

> **教-学-评一体化** | 类 QuickForm 的 Moodle 在线练习活动模块

---

## 📌 项目简介

`mod_quickpractice` 是一个面向中国中小学和高等院校的 **Moodle 活动模块插件**，灵感来自温州科技高级中学的 [QuickForm](https://quickform.cc) 项目，并深度融合 Moodle 生态能力，实现：

- **学生端**：便捷答题、即时反馈、查看个人得分与排名
- **教师端**：实时看板、班级分析、成绩排名、CSV 导出
- **题库管理**：GIFT 格式导入、从 Quiz 模块迁移、手动编辑
- **评估报告**：自动生成知识点分析、薄弱点识别、教学建议
- **练习生成器**：按难度/分类/题型自动从题库组卷

---

## 🎯 功能清单

### 学生端（Student View）

| 功能   | 说明                           |
| ---- | ---------------------------- |
| 答题页  | 支持单选、多选、判断、简答、计算、配对、论述 7 种题型 |
| 计时器  | 倒计时显示，时间到自动提交                |
| 即时反馈 | 提交后逐题显示正确答案与解析               |
| 成绩卡片 | 百分制得分、及格/未及格标识               |
| 班级排名 | 同班级成绩排名（匿名/实名可配置）            |
| 历史记录 | 查看所有历史作答记录                   |

### 教师看板（Teacher Dashboard）

| 功能     | 说明                           |
| ------ | ---------------------------- |
| 汇总统计   | 参与人数、平均分、及格率、平均用时            |
| 成绩分布图  | 5 段柱状图（Chart.js 渲染）          |
| 题目正确率图 | 各题横向条形图                      |
| 班级筛选   | 按班级（从用户姓名自动提取）筛选             |
| 学生明细   | 姓名、班级、得分、用时、提交时间             |
| 查看详情   | 点击进入学生的答题详情页                 |
| CSV 导出 | 一键导出 Excel 兼容 CSV（UTF-8 BOM） |

### 题库管理（Question Bank）

| 功能      | 说明                              |
| ------- | ------------------------------- |
| 手动添加    | 支持 7 种题型，含选项/答案/反馈/难度/分类        |
| GIFT 导入 | 支持 Moodle 标准 GIFT 格式，可上传文件或粘贴文本 |
| Quiz 导入 | 从同课程的 Quiz 模块迁移题目               |
| 编辑/删除   | 在线编辑题目内容                        |

### 评估报告（Assessment Report）

| 功能    | 说明                      |
| ----- | ----------------------- |
| 一键生成  | 自动计算并生成结构化教学报告          |
| 总体概况  | 人数、均分、及格率、最高/最低分、平均用时   |
| 题目分析  | 每题正确率及难度标注              |
| 薄弱知识点 | 按分类统计，自动识别正确率低于 60% 的类别 |
| 教学建议  | 基于数据自动生成 3-5 条文字建议      |
| 历史报告  | 保存并随时查阅历史报告             |

### 练习生成器（Practice Generator）

| 功能    | 说明                          |
| ----- | --------------------------- |
| 按条件筛题 | 支持按难度、分类、题型筛选               |
| 随机组卷  | 从题库随机抽取指定数量题目               |
| 一键生成  | 自动在课程中创建新的 QuickPractice 活动 |
| 题库结构图 | 展示当前题库各题型/难度分布              |

---

## 📁 目录结构

```
mod_quickpractice/
├── version.php                    # 版本信息
├── lib.php                        # Moodle API 接口函数
├── mod_form.php                   # 活动设置表单
├── index.php                      # 课程活动列表页
├── view.php                       # 学生入口/首页
├── attempt.php                    # 答题页
├── result.php                     # 结果/复习页
├── report.php                     # 教师看板
├── ranking.php                    # 班级排行榜
├── assessment.php                 # 评估报告
├── generator.php                  # 练习生成器
├── questionbank.php               # 题库管理
├── export.php                     # CSV 导出
├── styles.css                     # 样式文件
│
├── db/
│   ├── install.xml                # 数据库表结构（XMLDB）
│   └── access.php                 # 权限定义
│
├── lang/
│   ├── zh_cn/quickpractice.php    # 简体中文语言包
│   └── en/quickpractice.php       # 英文语言包
│
├── classes/
│   ├── local/
│   │   ├── attempt_manager.php    # 作答会话管理
│   │   ├── grader.php             # 自动评分引擎
│   │   ├── question_renderer.php  # 题目渲染器
│   │   ├── gift_importer.php      # GIFT 格式解析器
│   │   └── report_generator.php   # 评估报告生成器
│   └── event/
│       └── course_module_viewed.php
│
└── amd/src/
    ├── attempt_timer.js           # 倒计时 AMD 模块
    └── report_charts.js           # Chart.js 图表 AMD 模块
```

---

## 🗄️ 数据库表

| 表名                         | 说明              |
| -------------------------- | --------------- |
| `quickpractice`            | 练习活动实例          |
| `quickpractice_questions`  | 题库题目            |
| `quickpractice_attempts`   | 答题会话（含班级、IP、得分） |
| `quickpractice_responses`  | 每题作答明细          |
| `quickpractice_reports`    | 保存的评估报告         |
| `quickpractice_categories` | 题目分类            |

---

## 🔧 安装说明

### 系统要求

- Moodle 4.0+
- PHP 7.4 / 8.0 / 8.1 / 8.2
- MySQL 5.7+ / PostgreSQL 12+

### 安装步骤

```bash
# 1. 将插件目录复制到 Moodle 的 mod/ 目录
cp -r mod_quickpractice /path/to/moodle/mod/

# 2. 修复权限
chown -R www-data:www-data /path/to/moodle/mod/mod_quickpractice

# 3. 登录 Moodle 管理员账号，访问：
#    网站管理 → 通知 → 完成数据库升级
```

或通过 Moodle **插件安装器** 上传 zip 包安装。

---

## 🏫 班级识别机制

本插件从用户信息中自动提取班级名称，优先级如下：

1. **`department` 字段**（推荐）：Moodle 用户档案 → 部门
2. **姓名正则匹配**：识别 `高一3班-张三` / `2024级3班 李四` 格式
3. **`institution` 字段**：机构名称

建议将 Moodle 用户的 **"部门 (department)"** 字段填写为班级名称（如 `高一(3)班`），以获得最佳班级分组效果。

---

## 📥 GIFT 格式示例

```
// 单选题
::Q1:: 中国的首都是哪里？{
=北京
~上海
~广州
~深圳
}

// 判断题
::Q2:: 地球围绕太阳转。{TRUE}

// 简答题
::Q3:: 氧的化学符号是？{=O}

// 配对题
::Q4:: 请配对以下首都和国家{
=中国 -> 北京
=法国 -> 巴黎
=日本 -> 东京
}

// 数值题（允许误差±5）
::Q5:: 光速约为多少 km/s？{#300000:5000}
```

---

## 🔐 权限说明

| 权限     | 学生  | 教师  | 管理编辑教师 | 管理员 |
| ------ |:---:|:---:|:------:|:---:|
| 查看练习   | ✅   | ✅   | ✅      | ✅   |
| 参加答题   | ✅   | —   | —      | —   |
| 查看自己结果 | ✅   | ✅   | ✅      | ✅   |
| 教师看板   | —   | ✅   | ✅      | ✅   |
| 管理题库   | —   | —   | ✅      | ✅   |
| 导入题目   | —   | —   | ✅      | ✅   |
| 生成评估报告 | —   | ✅   | ✅      | ✅   |
| 手动评分   | —   | ✅   | ✅      | ✅   |

---

## 🧩 教-学-评一体化对照

| 环节                | 功能对应                   |
| ----------------- | ---------------------- |
| **教（Teaching）**   | 练习生成器→组卷，题库按知识点分类管理    |
| **学（Learning）**   | 学生端答题、即时反馈、错题解析，可多次作答  |
| **评（Assessment）** | 教师看板、评估报告、薄弱点诊断、教学建议生成 |

---

## 🛠️ 开发说明

### 自动评分支持

- **单选/判断/简答/数值**：全自动评分
- **多选**：支持部分得分
- **配对**：按对数比例得分
- **论述/主观题**：标记为"需手动评分"，教师可在看板中进入详情手动打分

### 前端技术

- CSS：纯 CSS3 Variables + Flexbox/Grid，兼容 Bootstrap 4/5
- JS：AMD 模块（Moodle 标准），依赖 jQuery（Moodle 内置）和 Chart.js（Moodle 4.x 内置）

---

## 📄 License

GPL v3 or later — https://www.gnu.org/copyleft/gpl.html

---

*本插件由 Robin liu基于 Moodle 开发规范编
