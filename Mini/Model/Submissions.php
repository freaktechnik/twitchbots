<?php

namespace Mini\Model;

use PDO;

/* CREATE TABLE IF NOT EXISTS submissions (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    twitch_id varchar(20) NOT NULL,
    name varchar(535) CHARACTER SET utf8 NOT NULL,
    description text NOT NULL,
    date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    type int(1) unsigned NOT NULL DEFAULT 0,
    channel varchar(535) CHARACTER SET utf8 DEFAULT NULL,
    channel_id varchar(20) DEFAULT NULL,
    offline boolean DEFAULT NULL,
    online boolean DEFAULT NULL,
    ismod boolean DEFAULT NULL,
    following int(10) unsigned DEFAULT NULL,
    following_channel boolean DEFAULT NULL,
    bio text DEFAULT NULL,
    vods boolean DEFAULT NULL,
    verified boolean DEFAULT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARSET=utf8 */

class Submissions extends PaginatingStore {
    const SUBMISSION = 0;
    const CORRECTION = 1;

    function __construct(PingablePDO $db, int $pageSize = 50)
    {
        parent::__construct($db, "submissions", $pageSize);
    }

    public function append(string $id, string $username, $type, int $correction = self::SUBMISSION, string $channel = NULL, string $channelId = NULL)
    {
        $query = $this->prepareInsert("(twitch_id,name,description,type,channel,channel_id) VALUES (?,?,?,?,?,?)");
        $params = array($id, $username, $type, $correction, $channel, $channelId);

        $query->execute($params);
    }

    public function getSubmissions(int $type = NULL): array
    {
        $condition = "ORDER BY score DESC, online DESC, date DESC";
        $params = array();
        if(isset($type)) {
            $condition = "WHERE type=? ".$condition;
        }
        $query = $this->prepareSelect("*, (IFNULL(offline,0) + IFNULL(ismod,0) + COALESCE(following<10,0) - IFNULL(vods,1) - (description REGEXP '^[^0-9].*$') - (2 - IFNULL(online+online,0))) AS score", $condition);
        if(isset($type)) {
            $query->bindValue(1, $type, PDO::PARAM_INT);
        }
        $query->execute();

        return $query->fetchAll();
    }

    public function getSubmission(int $id)
    {
        $query = $this->prepareSelect("*", "WHERE id=?");
        $query->execute(array($id));

        return $query->fetch();
    }

    public function has(string $id, int $type = NULL, string $description = NULL)
    {
        $where = "WHERE twitch_id=?";
        $params = array($id);

        if(!empty($type)) {
            $where .= " AND type=?";
            $params[] = $type;
        }
        if(!empty($description)) {
            $where .= " AND description=?";
            $params[] = $description;
        }
        $query = $this->prepareSelect("*", $where);
        $query->execute($params);

        return !empty($query->fetch());
    }

    public function hasSubmission(string $id): bool
    {
        return $this->has($id, self::SUBMISSION);
    }


    public function hasCorrection(string $id,  string $description): bool
    {
        return $this->has($id, self::CORRECTION, $description);
    }


    public function setInChat(string $id, $inChannel, bool $live)
    {
        if($live) {
            $sql = "online=? WHERE id=?";
        }
        else {
            $sql = "offline=? WHERE id=?";
        }

	    $query = $this->prepareUpdate($sql);
	    $query->execute(array($inChannel, $id));
    }

    public function setModded(int $id, $isMod)
    {
        $sql = "ismod=? WHERE id=?";
        $query = $this->prepareUpdate($sql);
        $query->execute(array($isMod, $id));
    }

    public function setFollowing(int $id, int $followingCount = null)
    {
        $sql = "following=? WHERE id=?";
        $query = $this->prepareUpdate($sql);
        $query->execute(array($followingCount, $id));
    }

    public function setFollowingChannel(int $id, $followingChannel = null)
    {
        $sql = "following_channel=? WHERE id=?";
        $query = $this->prepareUpdate($sql);
        $query->execute(array($followingChannel, $id));
    }

    public function setBio(int $id, string $bio = NULL)
    {
        if(!empty($bio)) {
            $sql = "bio=? WHERE id=?";
            $query = $this->prepareUpdate($sql);
            $query->execute(array($bio, $id));
        }
    }

    public function setHasVODs(int $id, bool $hasVODs)
    {
        $sql = "vods=? WHERE id=?";
        $query = $this->prepareUpdate($sql);
        $query->execute(array($hasVODs, $id));
    }

    public function setVerified(int $id, bool $verified) {
        $sql = 'verified=? WHERE id=?';
        $query = $this->prepareUpdate($sql);
        $query->execute([ $verified, $id ]);
    }

    public function setTwitchID(int $id, int $twitchID, string $type = "twitch")
    {
        $sql = $type."_id=? WHERE id=?";
        $query = $this->prepareUpdate($sql);
        $query->execute([$twitchID, $id]);
    }

    public function removeSubmission(int $id)
    {
        $query = $this->prepareDelete("WHERE id=?");
        $query->execute(array($id));
    }

    public function removeSubmissions(string $username, string $description = NULL)
    {
        $condition = "WHERE name=?";
        $args = array($username);
        if(!empty($description)) {
            $condition .= " AND description=?";
            $args[] = $description;
        }
        $query = $this->prepareDelete($condition);
        $query->execute($args);
    }
}
